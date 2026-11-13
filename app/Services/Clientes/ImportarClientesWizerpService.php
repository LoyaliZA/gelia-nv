<?php

namespace App\Services\Clientes;

use App\Models\CambioListaImportacionCliente;
use App\Models\ConflictoImportacionCliente;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ErroresImportacionCliente;
use App\Models\FilaRevisionImportacionCliente;
use App\Models\HistorialMontoCliente;
use App\Models\ImportacionCliente;
use App\Models\ImporteProvisionalSolicitud;
use App\Models\User;
use App\Notifications\AlertaLimiteCreditoSuperadoMasivoNotification;
use App\Services\Escalonamiento\EscalonamientoAutoridad;
use App\Services\Mobile\MobileSyncPublicationService;
use App\Services\Mobile\MobileSyncSuppression;
use App\Services\Solicitudes\ImporteProvisionalSolicitudService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImportarClientesWizerpService
{
    private int $bloqueosImportacion = 0;

    private mixed $bloqueoCache = null;

    private int $cursorFila = 0;

    /** @var list<array<string, mixed>> */
    private array $ascensosDetalle = [];

    /** @var list<mixed> */
    private array $alertasCredito = [];

    private float $loteInicio = 0.0;

    /** @var array<string, float> */
    private array $tiemposMs = ['lectura' => 0, 'consultas' => 0, 'escritura' => 0, 'sincronizacion' => 0];

    public function __construct(protected ProcesarFilaClienteAction $procesador) {}

    public function duranteBloqueo(callable $accion): mixed
    {
        $this->adquirirBloqueo();
        try {
            return $accion();
        } finally {
            $this->liberarBloqueo();
        }
    }

    public function ejecutar(UploadedFile $archivo, ?ImportacionCliente $importacion = null, int $desdeFila = 0, ?int $limite = null): array
    {
        $inicio = microtime(true);
        $stats = array_fill_keys(['leidas', 'procesadas', 'creados', 'actualizados', 'sin_cambios', 'omitidas', 'errores',
            'ascensos', 'descensos', 'activados', 'clientes_marcados_inactivos', 'cambios_lista', 'conflictos'], 0);
        $errores = [];
        $this->cursorFila = $desdeFila;
        $this->ascensosDetalle = [];
        $this->alertasCredito = [];
        $this->tiemposMs = ['lectura' => 0, 'consultas' => 0, 'escritura' => 0, 'sincronizacion' => 0];
        if ($importacion) {
            $memoria = $importacion->resumen ?? [];
            if ($desdeFila > 0) {
                $inicio = (float) ($memoria['inicio_ts'] ?? $inicio);
                $this->ascensosDetalle = array_values($memoria['ascensos_detalle'] ?? []);
                $this->alertasCredito = array_values($memoria['alertas_credito'] ?? []);
                $contadores = is_array($importacion->contadores) ? $importacion->contadores : $memoria;
                foreach (array_keys($stats) as $clave) {
                    if ($clave === 'leidas') {
                        continue;
                    }
                    if (isset($contadores[$clave]) && is_numeric($contadores[$clave])) {
                        $stats[$clave] = (int) $contadores[$clave];
                    }
                }
            } else {
                $memoria['inicio_ts'] = $inicio;
                $importacion->resumen = $memoria;
            }
        }
        try {
            $this->adquirirBloqueo();
            $this->loteInicio = microtime(true);
            $path = $importacion
                ? Storage::disk('local')->path($importacion->ruta_almacenamiento)
                : $archivo->getRealPath();
            $lectura = microtime(true);
            $fuente = $this->fuenteFilas($importacion, (string) $path, $desdeFila, $limite);
            $this->tiemposMs['lectura'] += (microtime(true) - $lectura) * 1000;
            $stats['leidas'] = $fuente['leidas'];
            $revisionImportacion = ($importacion && is_array($importacion->resumen)) ? ($importacion->resumen['revision'] ?? null) : null;
            $catalogoAprobado = is_array($revisionImportacion) ? ($revisionImportacion['catalogo'] ?? null) : null;
            if (is_string($catalogoAprobado) && ! hash_equals($catalogoAprobado, $this->huellaCatalogoRevision())) {
                throw ValidationException::withMessages(['archivo' => 'El catálogo cambió después de la revisión. Hay que revisar el archivo de nuevo.']);
            }
            $estadoAprobado = is_array($revisionImportacion) ? ($revisionImportacion['estado_clientes'] ?? null) : null;
            $consulta = microtime(true);
            $this->sincronizarListasWizerp();
            $mapa = $this->cargarMapaVendedoras();
            $listas = CatalogoListaDescuento::orderByDesc('monto_requerido')->get();
            $this->tiemposMs['consultas'] += (microtime(true) - $consulta) * 1000;
            Cache::put('import_clientes_en_curso', true, now()->addHours(2));
            $manejadas = 0;
            $hayMas = $fuente['hay_mas'];
            $importaCodigoLista = in_array('codigo_lista', $fuente['headers'], true);
            foreach ($fuente['filas'] as $fila) {
                if (! $fuente['acotado'] && (int) $fila['numero_fila'] <= $desdeFila) {
                    continue;
                }
                if (! $fuente['acotado'] && $limite !== null && $manejadas >= $limite) {
                    $hayMas = true;
                    break;
                }
                $manejadas++;
                $this->cursorFila = (int) $fila['numero_fila'];
                if ($fila['duplicada']) {
                    $stats['omitidas']++;

                    continue;
                }
                try {
                    $resultado = DB::transaction(function () use ($fila, $listas, $mapa, $importaCodigoLista, $importacion, $stats, $estadoAprobado) {
                        $cliente = Cliente::query()->where('numero_cliente', $fila['data']['numero_cliente'])->lockForUpdate()->first();
                        if ($cliente && (float) $cliente->monto_credito_autorizado > 0) {
                            $cliente->load('facturaCobranzaActiva');
                        }
                        if (is_array($estadoAprobado) && array_key_exists($fila['data']['numero_cliente'], $estadoAprobado)) {
                            $this->exigirEstadoAprobado($cliente, $estadoAprobado[$fila['data']['numero_cliente']]);
                        }
                        $antes = $cliente ? clone $cliente : null;
                        $this->exigirCorteAplicable($importacion, $cliente, $fila['data']);
                        $coleccion = collect($cliente ? [$cliente->numero_cliente => $cliente] : []);
                        $historial = [];
                        $inactivos = 0;
                        $alertasFila = [];
                        MobileSyncSuppression::begin();
                        try {
                            $escritura = microtime(true);
                            $cambio = $this->procesador->ejecutar($fila['data'], $listas, $mapa, $coleccion,
                                $historial, $importaCodigoLista, $inactivos, $alertasFila, $importacion?->id, $importacion?->usuario_id);
                            $efectoFila = $this->procesador->efecto;
                            $despues = $coleccion->get($fila['data']['numero_cliente']);
                            if (! $despues && ($efectoFila['omitida'] || $efectoFila['conflictos'] !== [])) {
                                $siguiente = $stats;
                                $siguiente['omitidas'] += $efectoFila['omitida'] ? 1 : 0;
                                $siguiente['conflictos'] += count($efectoFila['conflictos']);
                                $siguiente['procesadas']++;
                                foreach ($efectoFila['conflictos'] as $conflicto) {
                                    $this->registrarConflicto($importacion, $fila, $antes, null, $conflicto);
                                }

                                return ['stats' => $siguiente, 'cambio' => null, 'alertas' => []];
                            }
                            if (! $despues) {
                                throw ValidationException::withMessages(['numero_cliente' => 'La fila no produjo un cliente.']);
                            }
                            if ($historial !== []) {
                                HistorialMontoCliente::insert($historial);
                            }
                            $this->cerrarProvisionalesSiCorte($importacion, $despues, $fila['data']);
                            $this->registrarCorteAplicado($importacion, $despues, $fila['data']);
                            if ($cambio && $importacion) {
                                CambioListaImportacionCliente::create([
                                    'importacion_cliente_id' => $importacion->id, 'numero_cliente' => $cambio['numero_cliente'],
                                    'nombre_cliente' => $cambio['nombre'], 'lista_anterior' => $cambio['lista_anterior'],
                                    'lista_nueva' => $cambio['lista_nueva'], 'tipo_cambio' => $cambio['tipo_cambio'],
                                    'codigo_lista' => $cambio['codigo_lista'], 'monto_nuevo' => $cambio['monto_nuevo'],
                                ]);
                            }
                            $this->tiemposMs['escritura'] += (microtime(true) - $escritura) * 1000;
                            $efecto = $this->procesador->efecto;
                            if ($efecto['omitida']) {
                                $categoria = 'omitidas';
                            } elseif (! $antes) {
                                $categoria = 'creados';
                            } elseif ($despues->wasChanged()) {
                                $categoria = 'actualizados';
                            } else {
                                $categoria = 'sin_cambios';
                            }
                            foreach ($efecto['conflictos'] as $conflicto) {
                                $this->registrarConflicto($importacion, $fila, $antes, $despues, $conflicto);
                            }
                            $siguiente = $stats;
                            $siguiente['conflictos'] += count($efecto['conflictos']);
                            $siguiente[$categoria]++;
                            $siguiente['procesadas']++;
                            $siguiente['clientes_marcados_inactivos'] += $inactivos;
                            $siguiente['activados'] += ! $despues->es_inactivo && (! $antes || $antes->es_inactivo) ? 1 : 0;
                            $siguiente['cambios_lista'] += $cambio ? 1 : 0;
                            if (($cambio['tipo_cambio'] ?? '') === CambioListaImportacionCliente::TIPO_ASCENSO) {
                                $siguiente['ascensos']++;
                            } elseif (($cambio['tipo_cambio'] ?? '') === CambioListaImportacionCliente::TIPO_DESCENSO) {
                                $siguiente['descensos']++;
                            }
                            $sincronizacion = microtime(true);
                            app(MobileSyncPublicationService::class)->publishDiffColeccion(
                                collect($antes ? [$antes->id => $antes] : []), collect([$despues->id => $despues]));
                            $this->tiemposMs['sincronizacion'] += (microtime(true) - $sincronizacion) * 1000;

                            return ['stats' => $siguiente, 'cambio' => $cambio, 'alertas' => $categoria === 'sin_cambios' ? [] : $alertasFila];
                        } finally {
                            MobileSyncSuppression::end();
                        }
                    });
                    $stats = $resultado['stats'];
                    if (($resultado['cambio']['tipo_cambio'] ?? '') === CambioListaImportacionCliente::TIPO_ASCENSO) {
                        $this->ascensosDetalle[] = $resultado['cambio'];
                    }
                    $this->alertasCredito = array_merge($this->alertasCredito, $resultado['alertas']);
                } catch (\Throwable $e) {
                    $stats['errores']++;
                    $detalles = $e instanceof ValidationException ? $e->errors() : ['fila' => [$e->getMessage()]];
                    foreach ($detalles as $campo => $mensajes) {
                        $error = ['numero_fila' => $fila['numero_fila'], 'numero_cliente' => $fila['data']['numero_cliente'] ?: null,
                            'campo' => $campo, 'mensaje' => $this->sanitizarTextoImportacion(implode(' ', $mensajes))];
                        $errores[] = $error;
                        if ($importacion) {
                            ErroresImportacionCliente::create(['importacion_cliente_id' => $importacion->id] + $error);
                        }
                    }
                    // Una caída de conexión/infraestructura detiene el archivo: no aparentar errores de contenido.
                    if ($e instanceof \Error || ($e instanceof QueryException
                        && ! in_array((string) $e->getCode(), ['23000', '22001', '22003'], true))) {
                        throw $e;
                    }
                }
            }
            if ($hayMas) {
                $stats['duracion_seg'] = round(microtime(true) - $inicio, 2);
                $this->guardarProgreso($importacion, $stats, 'en_curso');

                return $this->resultadoEjecucion($stats, 'en_curso', $errores, true);
            }
            $estado = $stats['errores'] === 0 ? 'completo' : ($stats['procesadas'] > 0 ? 'parcial' : 'fallido');
            $stats['duracion_seg'] = round(microtime(true) - $inicio, 2);
            $this->guardarProgreso($importacion, $stats, $estado);
        } catch (\Throwable $e) {
            if ($limite !== null && $e instanceof ValidationException && str_contains((string) collect($e->errors())->flatten()->first(), 'en curso')) {
                throw $e;
            }
            $stats['no_aplicadas'] = max(0, $stats['leidas'] - $stats['procesadas'] - $stats['omitidas'] - $stats['errores']);
            if ($importacion) {
                $this->guardarProgreso($importacion, $stats, $stats['procesadas'] > 0 ? 'parcial' : 'fallido', $e->getMessage());
            }
            throw $e;
        } finally {
            $this->liberarBloqueo();
        }
        if ($this->alertasCredito !== []) {
            Notification::send(User::permission('cobranza.recibir_alertas')->get(), new AlertaLimiteCreditoSuperadoMasivoNotification($this->alertasCredito));
        }

        return $this->resultadoEjecucion($stats, $estado, $errores, false);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  list<array<string, mixed>>  $errores
     * @return array<string, mixed>
     */
    private function resultadoEjecucion(array $stats, string $estado, array $errores, bool $hayMas): array
    {
        return [
            'ascensos' => $this->ascensosDetalle,
            'clientes_marcados_inactivos' => $stats['clientes_marcados_inactivos'],
            'stats' => $stats,
            'estado' => $estado,
            'errores_detalle' => array_slice($errores, 0, 100),
            'detalle_limitado' => count($errores) > 100,
            'hay_mas' => $hayMas,
            'cursor_fila' => $this->cursorFila,
        ];
    }

    public function exigirCorteAplicable(?ImportacionCliente $importacion, ?Cliente $cliente, array $data): void
    {
        if (! $importacion || ! $cliente || $importacion->modalidad_carga !== ImportacionCliente::MODALIDAD_CORTE || ! $importacion->fecha_corte) {
            return;
        }
        if (app(MontoWizerp::class)->interpretar($data['monto_venta_actual'] ?? null) === null) {
            return;
        }
        if ($this->corteAnteriorAlOficial($cliente, $importacion->fecha_corte, $importacion->id)) {
            throw ValidationException::withMessages([
                'fecha_corte' => 'El corte es anterior a la última actualización oficial de este cliente. No se escribe el monto ni se cierran provisionales.',
            ]);
        }
    }

    private function cerrarProvisionalesSiCorte(?ImportacionCliente $importacion, Cliente $cliente, array $data): void
    {
        if (! $importacion || $importacion->modalidad_carga !== ImportacionCliente::MODALIDAD_CORTE || ! $importacion->fecha_corte) {
            return;
        }
        if (app(MontoWizerp::class)->interpretar($data['monto_venta_actual'] ?? null) === null) {
            return;
        }
        if (app(EscalonamientoAutoridad::class)->clienteGobernadoPorModulo($cliente)) {
            return;
        }

        app(ImporteProvisionalSolicitudService::class)->cerrarPorCorte(
            $cliente,
            $importacion->fecha_corte,
            $importacion->id,
            $importacion->usuario_id,
        );
    }

    private function registrarCorteAplicado(?ImportacionCliente $importacion, Cliente $cliente, array $data): void
    {
        if (! $importacion || $importacion->modalidad_carga !== ImportacionCliente::MODALIDAD_CORTE || ! $importacion->fecha_corte) {
            return;
        }
        if (app(MontoWizerp::class)->interpretar($data['monto_venta_actual'] ?? null) === null) {
            return;
        }
        if (app(EscalonamientoAutoridad::class)->clienteGobernadoPorModulo($cliente)) {
            return;
        }

        Cliente::query()
            ->whereKey($cliente->id)
            ->where(function ($consulta) use ($importacion) {
                $consulta->whereNull('fecha_ultimo_corte_administrativo')
                    ->orWhere('fecha_ultimo_corte_administrativo', '!=', $importacion->fecha_corte->format('Y-m-d H:i:s'));
            })
            ->update([
                'fecha_ultimo_corte_administrativo' => $importacion->fecha_corte->format('Y-m-d H:i:s'),
            ]);
    }

    private function corteAnteriorAlOficial(Cliente $cliente, \Carbon\Carbon $corte, ?int $exceptoImportacionId): bool
    {
        $ultimo = $this->ultimoCorteAplicado($cliente, $exceptoImportacionId);

        return $ultimo !== null && $ultimo->greaterThan($corte);
    }

    private function ultimoCorteAplicado(Cliente $cliente, ?int $exceptoImportacionId): ?\Carbon\Carbon
    {
        $porImporte = ImportacionCliente::query()
            ->where('modalidad_carga', ImportacionCliente::MODALIDAD_CORTE)
            ->whereNotNull('fecha_corte')
            ->when($exceptoImportacionId, fn ($consulta) => $consulta->where('id', '!=', $exceptoImportacionId))
            ->whereIn('id', HistorialMontoCliente::query()
                ->where('cliente_id', $cliente->id)
                ->whereNotNull('importacion_cliente_id')
                ->select('importacion_cliente_id'))
            ->max('fecha_corte');
        $porCierre = ImporteProvisionalSolicitud::query()
            ->where('cliente_id', $cliente->id)
            ->whereNotNull('fecha_corte')
            ->when($exceptoImportacionId, function ($consulta) use ($exceptoImportacionId) {
                $consulta->where(function ($consulta) use ($exceptoImportacionId) {
                    $consulta->whereNull('importacion_cliente_id')
                        ->orWhere('importacion_cliente_id', '!=', $exceptoImportacionId);
                });
            })
            ->max('fecha_corte');
        $porCliente = Cliente::query()->whereKey($cliente->id)->value('fecha_ultimo_corte_administrativo');
        $fechas = array_filter([$porImporte, $porCierre, $porCliente]);
        if ($fechas === []) {
            return null;
        }

        return collect($fechas)
            ->map(fn ($fecha) => \Carbon\Carbon::parse($fecha))
            ->sortByDesc(fn (\Carbon\Carbon $fecha) => $fecha->getTimestamp())
            ->first();
    }

    private function guardarProgreso(?ImportacionCliente $importacion, array $stats, string $estado, ?string $mensaje = null): void
    {
        if (! $importacion) {
            return;
        }
        $revision = $importacion->resumen['revision'] ?? null;
        $conservar = [];
        foreach (['conteos', 'bloqueos', 'avisos', 'muestras', 'inicio_ts', 'tiempos'] as $clave) {
            if (array_key_exists($clave, $importacion->resumen ?? [])) {
                $conservar[$clave] = $importacion->resumen[$clave];
            }
        }
        $resumen = $stats + $conservar;
        $resumen['ascensos_detalle'] = $this->ascensosDetalle;
        $resumen['alertas_credito'] = $this->alertasCredito;
        if ($this->cursorFila > 0) {
            $resumen['cursor_fila'] = $this->cursorFila;
        }
        if ($this->loteInicio > 0) {
            $this->tiemposMs['lote'] = (microtime(true) - $this->loteInicio) * 1000;
            $this->loteInicio = 0;
        }
        $tiempos = is_array($resumen['tiempos'] ?? null) ? $resumen['tiempos'] : [];
        foreach ($this->tiemposMs as $clave => $ms) {
            $tiempos[$clave.'_ms'] = round((float) ($tiempos[$clave.'_ms'] ?? 0) + $ms, 1);
        }
        $this->tiemposMs = array_fill_keys(array_keys($this->tiemposMs), 0);
        $resumen['tiempos'] = $tiempos;
        if (is_array($revision)) {
            $resumen['revision'] = $revision;
        }
        $importacion->update(['estado' => $estado, 'resumen' => $resumen, 'mensaje_fatal' => $mensaje === null ? null : $this->sanitizarTextoImportacion($mensaje),
            'filas_leidas' => $stats['leidas'], 'filas_procesadas' => $stats['procesadas'], 'filas_omitidas' => $stats['omitidas'],
            'errores' => $stats['errores'], 'ascensos' => $stats['ascensos'], 'descensos' => $stats['descensos'],
            'clientes_marcados_inactivos' => $stats['clientes_marcados_inactivos'], 'duracion_seg' => $stats['duracion_seg'] ?? null,
            'cursor_fila' => $this->cursorFila, 'contadores' => $stats]);
        $importacion->resumen = $resumen;
    }

    /**
     * @param  array<string, int|float>  $stats
     */
    /**
     * @param  array<string, mixed>  $fila
     * @param  array{alcance: string, motivo: string, solicitud_id: ?int}  $conflicto
     */
    private function registrarConflicto(?ImportacionCliente $importacion, array $fila, ?Cliente $antes, ?Cliente $despues, array $conflicto): void
    {
        if (! $importacion) {
            return;
        }
        $vigente = $antes ? [
            'lista_actual_id' => $antes->lista_actual_id,
            'es_menudeo' => (bool) $antes->es_menudeo,
            'es_inactivo' => (bool) $antes->es_inactivo,
        ] : null;
        ConflictoImportacionCliente::query()->create([
            'importacion_cliente_id' => $importacion->id,
            'cliente_id' => $antes?->id ?? $despues?->id,
            'numero_cliente' => (string) ($fila['data']['numero_cliente'] ?? ''),
            'numero_fila' => (int) ($fila['numero_fila'] ?? 0),
            'alcance' => $conflicto['alcance'],
            'estado' => ConflictoImportacionCliente::PENDIENTE,
            'motivo' => $conflicto['motivo'],
            'valor_vigente' => $vigente,
            'valor_propuesto' => [
                'codigo_lista' => $fila['data']['codigo_lista'] ?? null,
                'monto_venta_actual' => $fila['data']['monto_venta_actual'] ?? null,
            ],
            'origen_vigente' => $antes?->modalidad_origen,
            'origen_propuesto' => 'importacion',
            'solicitud_tag_id' => $conflicto['solicitud_id'],
        ]);
    }

    private function marcarAvance(?ImportacionCliente $importacion, array $stats): void
    {
        if (! $importacion) {
            return;
        }

        $importacion->newQuery()->whereKey($importacion->id)->update([
            'estado' => 'en_curso',
            'cursor_fila' => $this->cursorFila,
            'filas_leidas' => $stats['leidas'],
            'filas_procesadas' => $stats['procesadas'],
            'filas_omitidas' => $stats['omitidas'],
            'errores' => $stats['errores'],
            'ascensos' => $stats['ascensos'],
            'descensos' => $stats['descensos'],
            'clientes_marcados_inactivos' => $stats['clientes_marcados_inactivos'],
            'contadores' => json_encode($stats),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{filas: list<array<string, mixed>>, headers: list<string>, leidas: int, hay_mas: bool, acotado: bool}
     */
    private function fuenteFilas(?ImportacionCliente $importacion, string $path, int $desdeFila, ?int $limite): array
    {
        $material = $this->filasMaterializadas($importacion, $path, $desdeFila, $limite);
        if ($material !== null) {
            return $material;
        }

        $validado = app(ValidarArchivoClientesWizerp::class)->leer($path);

        return [
            'filas' => $validado['filas'],
            'headers' => $validado['headers'],
            'leidas' => count($validado['filas']),
            'hay_mas' => false,
            'acotado' => false,
        ];
    }

    /**
     * @return ?array{filas: list<array<string, mixed>>, headers: list<string>, leidas: int, hay_mas: bool, acotado: bool}
     */
    private function filasMaterializadas(?ImportacionCliente $importacion, string $path, int $desdeFila, ?int $limite): ?array
    {
        if (! $importacion) {
            return null;
        }
        $hayDatos = FilaRevisionImportacionCliente::query()
            ->where('importacion_cliente_id', $importacion->id)
            ->whereNotNull('datos')
            ->exists();
        if (! $hayDatos) {
            return null;
        }

        $revision = is_array($importacion->resumen) ? ($importacion->resumen['revision'] ?? []) : [];
        $sha = (string) ($revision['archivo_sha256'] ?? '');
        $actual = is_file($path) ? (hash_file('sha256', $path) ?: '') : '';
        if ($sha === '' || ! hash_equals($sha, $actual)) {
            throw ValidationException::withMessages(['archivo' => 'El archivo ya no coincide con la revisión aprobada.']);
        }

        $consulta = FilaRevisionImportacionCliente::query()
            ->where('importacion_cliente_id', $importacion->id)
            ->where('numero_fila', '>', $desdeFila)
            ->orderBy('numero_fila');
        if ($limite !== null) {
            $consulta->limit($limite + 1);
        }
        $rows = $consulta->get(['numero_fila', 'datos']);
        $hayMas = $limite !== null && $rows->count() > $limite;
        if ($hayMas) {
            $rows = $rows->take($limite);
        }
        $filas = [];
        foreach ($rows as $row) {
            $datos = is_array($row->datos) ? $row->datos : [];
            $filas[] = [
                'numero_fila' => (int) $row->numero_fila,
                'data' => is_array($datos['data'] ?? null) ? $datos['data'] : [],
                'duplicada' => (bool) ($datos['duplicada'] ?? false),
            ];
        }
        $headers = is_array($revision['headers'] ?? null) ? $revision['headers'] : array_keys($filas[0]['data'] ?? []);
        $leidas = (int) $importacion->filas_leidas;
        if ($leidas === 0) {
            $leidas = FilaRevisionImportacionCliente::query()->where('importacion_cliente_id', $importacion->id)->count();
        }

        return [
            'filas' => $filas,
            'headers' => $headers,
            'leidas' => $leidas,
            'hay_mas' => $hayMas,
            'acotado' => true,
        ];
    }

    private function sanitizarTextoImportacion(?string $texto): string
    {
        return mb_substr(mb_scrub($texto ?? '', 'UTF-8'), 0, 4000);
    }

    public function cargarMapaVendedoras(): array
    {
        $mapa = [];
        $vendedoras = User::whereHas('roles', function ($q) {
            $q->where('name', 'colaborador');
        })->get(['id', 'name', 'username']);

        foreach ($vendedoras as $vendedora) {
            $mapa[strtoupper(trim($vendedora->name))] = $vendedora->id;
            if ($vendedora->username) {
                $mapa[strtoupper(trim($vendedora->username))] = $vendedora->id;
            }
        }

        return $mapa;
    }

    public function sincronizarListasWizerp(): void
    {
        foreach ($this->definicionesListasOficiales() as $lista) {
            CatalogoListaDescuento::firstOrCreate(
                ['nombre' => $lista['nombre']],
                ['monto_requerido' => $lista['monto_requerido'], 'activo' => 1]
            );
        }
    }

    public function listasParaRevision(): Collection
    {
        $existentes = CatalogoListaDescuento::query()->orderBy('nombre')->get();
        $nombres = $existentes->pluck('nombre')->all();
        $temporales = [];
        $id = -1;
        foreach ($this->definicionesListasOficiales() as $definicion) {
            if (in_array($definicion['nombre'], $nombres, true)) {
                continue;
            }
            $modelo = new CatalogoListaDescuento([
                'nombre' => $definicion['nombre'],
                'monto_requerido' => $definicion['monto_requerido'],
                'activo' => 1,
            ]);
            $modelo->id = $id--;
            $modelo->exists = false;
            $temporales[] = $modelo;
        }

        return $existentes->concat($temporales)->sortByDesc(fn ($lista) => (float) $lista->monto_requerido)->values();
    }

    public function huellaCatalogoRevision(): string
    {
        $lineas = $this->listasParaRevision()
            ->map(fn ($lista) => $lista->nombre.'|'.bcadd((string) $lista->monto_requerido, '0', 2))
            ->sort()
            ->values()
            ->all();

        return hash('sha256', implode("\n", $lineas));
    }

    public function huellaReglasRevision(): string
    {
        $autoridad = app(EscalonamientoAutoridad::class)->estaActiva() ? '1' : '0';

        return hash('sha256', 'matriz-clientes-2026-11-13|'.$autoridad);
    }

    /**
     * @return list<array{nombre: string, monto_requerido: float}>
     */
    private function definicionesListasOficiales(): array
    {
        return [
            ['nombre' => 'MAYOREO DIAMANTE', 'monto_requerido' => 80001.00],
            ['nombre' => 'MAYOREO ORO', 'monto_requerido' => 30001.00],
            ['nombre' => 'MAYOREO PLATA', 'monto_requerido' => 5001.00],
            ['nombre' => 'MAYOREO BRONCE', 'monto_requerido' => 0.01],
            ['nombre' => 'PUBLICO GENERAL', 'monto_requerido' => 0.00],
            ['nombre' => 'PLATAFORMAS', 'monto_requerido' => 999999.00],
            ['nombre' => 'COLABORADORES', 'monto_requerido' => 999999.00],
        ];
    }

    private function adquirirBloqueo(): void
    {
        if ($this->bloqueosImportacion > 0) {
            $this->bloqueosImportacion++;

            return;
        }
        if (DB::connection()->getDriverName() === 'mysql') {
            $adquirido = (int) DB::selectOne("SELECT GET_LOCK('gelia.importacion.clientes.wizerp', 0) AS adquirido")->adquirido === 1;
        } else {
            $this->bloqueoCache = Cache::lock('gelia.importacion.clientes.wizerp', 0);
            $adquirido = $this->bloqueoCache->get();
        }
        if (! $adquirido) {
            throw ValidationException::withMessages(['archivo' => 'Ya existe una importación de clientes en curso.']);
        }
        $this->bloqueosImportacion = 1;
    }

    private function liberarBloqueo(): void
    {
        if ($this->bloqueosImportacion === 0) {
            return;
        }
        $this->bloqueosImportacion--;
        if ($this->bloqueosImportacion > 0) {
            return;
        }
        Cache::forget('import_clientes_en_curso');
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::selectOne("SELECT RELEASE_LOCK('gelia.importacion.clientes.wizerp')");
        } elseif ($this->bloqueoCache) {
            $this->bloqueoCache->release();
            $this->bloqueoCache = null;
        }
    }

    private function exigirEstadoAprobado(?Cliente $cliente, mixed $esperado): void
    {
        if ($esperado === null) {
            if ($cliente) {
                throw ValidationException::withMessages(['numero_cliente' => 'El cliente ya existe y la revisión lo trataba como nuevo.']);
            }

            return;
        }
        if (! is_array($esperado) || ! $cliente) {
            throw ValidationException::withMessages(['numero_cliente' => 'El cliente de la revisión ya no está en el estado aprobado.']);
        }
        $attrs = $cliente->getAttributes();
        $actual = [
            'lista' => (string) ($attrs['lista_actual_id'] ?? ''),
            'monto' => bcadd((string) ($attrs['monto_venta_actual'] ?? '0'), '0', 2),
            'inactivo' => (int) ($attrs['es_inactivo'] ?? 0),
            'updated_at' => (string) ($attrs['updated_at'] ?? ''),
            'es_menudeo' => ClasificacionComercialCliente::bandera($attrs['es_menudeo'] ?? false),
            'origen' => (string) ($attrs['modalidad_origen'] ?? ''),
            'conversion_solicitud_id' => (string) ($attrs['conversion_solicitud_id'] ?? ''),
        ];
        if ($actual != $esperado) {
            throw ValidationException::withMessages(['numero_cliente' => 'El cliente cambió después de la revisión.']);
        }
    }
}
