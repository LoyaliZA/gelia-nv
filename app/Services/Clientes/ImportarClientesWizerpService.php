<?php

namespace App\Services\Clientes;

use App\Models\CambioListaImportacionCliente;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ErroresImportacionCliente;
use App\Models\HistorialMontoCliente;
use App\Models\ImportacionCliente;
use App\Models\User;
use App\Notifications\AlertaLimiteCreditoSuperadoMasivoNotification;
use App\Services\Mobile\MobileSyncPublicationService;
use App\Services\Mobile\MobileSyncSuppression;
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
            'ascensos', 'descensos', 'activados', 'clientes_marcados_inactivos', 'cambios_lista'], 0);
        $errores = [];
        $this->cursorFila = $desdeFila;
        $this->ascensosDetalle = [];
        $this->alertasCredito = [];
        if ($importacion) {
            $memoria = $importacion->resumen ?? [];
            if ($desdeFila > 0 && isset($memoria['inicio_ts'])) {
                $inicio = (float) $memoria['inicio_ts'];
                $this->ascensosDetalle = array_values($memoria['ascensos_detalle'] ?? []);
                $this->alertasCredito = array_values($memoria['alertas_credito'] ?? []);
            } else {
                $memoria['inicio_ts'] = $inicio;
                $importacion->resumen = $memoria;
            }
        }
        try {
            $this->adquirirBloqueo();
            $path = $importacion
                ? Storage::disk('local')->path($importacion->ruta_almacenamiento)
                : $archivo->getRealPath();
            // Preflight completo: ningún catálogo ni cliente se escribe antes de validar estructura.
            $validado = app(ValidarArchivoClientesWizerp::class)->leer($path);
            $stats['leidas'] = count($validado['filas']);
            if ($importacion && $desdeFila > 0 && is_array($importacion->resumen)) {
                foreach (array_keys($stats) as $clave) {
                    if ($clave === 'leidas') {
                        continue;
                    }
                    if (isset($importacion->resumen[$clave]) && is_numeric($importacion->resumen[$clave])) {
                        $stats[$clave] = (int) $importacion->resumen[$clave];
                    }
                }
            }
            $importaCodigoLista = in_array('codigo_lista', $validado['headers'], true);
            $revisionImportacion = ($importacion && is_array($importacion->resumen)) ? ($importacion->resumen['revision'] ?? null) : null;
            $catalogoAprobado = is_array($revisionImportacion) ? ($revisionImportacion['catalogo'] ?? null) : null;
            if (is_string($catalogoAprobado) && ! hash_equals($catalogoAprobado, $this->huellaCatalogoRevision())) {
                throw ValidationException::withMessages(['archivo' => 'El catálogo cambió después de la revisión. Hay que revisar el archivo de nuevo.']);
            }
            $estadoAprobado = is_array($revisionImportacion) ? ($revisionImportacion['estado_clientes'] ?? null) : null;
            $this->sincronizarListasWizerp();
            $mapa = $this->cargarMapaVendedoras();
            $listas = CatalogoListaDescuento::orderByDesc('monto_requerido')->get();
            Cache::put('import_clientes_en_curso', true, now()->addHours(2));
            $manejadas = 0;
            $hayMas = false;
            foreach ($validado['filas'] as $fila) {
                if ((int) $fila['numero_fila'] <= $desdeFila) {
                    continue;
                }
                if ($limite !== null && $manejadas >= $limite) {
                    $hayMas = true;
                    break;
                }
                $manejadas++;
                $this->cursorFila = (int) $fila['numero_fila'];
                if ($fila['duplicada']) {
                    $stats['omitidas']++;
                    $this->guardarProgreso($importacion, $stats, 'en_curso');

                    continue;
                }
                try {
                    $resultado = DB::transaction(function () use ($fila, $listas, $mapa, $importaCodigoLista, $importacion, $stats, $estadoAprobado) {
                        $cliente = Cliente::with('facturaCobranzaActiva')->where('numero_cliente', $fila['data']['numero_cliente'])->lockForUpdate()->first();
                        if (is_array($estadoAprobado) && array_key_exists($fila['data']['numero_cliente'], $estadoAprobado)) {
                            $this->exigirEstadoAprobado($cliente, $estadoAprobado[$fila['data']['numero_cliente']]);
                        }
                        $antes = $cliente ? clone $cliente : null;
                        $coleccion = collect($cliente ? [$cliente->numero_cliente => $cliente] : []);
                        $historial = [];
                        $inactivos = 0;
                        $alertasFila = [];
                        MobileSyncSuppression::begin();
                        try {
                            $cambio = $this->procesador->ejecutar($fila['data'], $listas, $mapa, $coleccion,
                                $historial, $importaCodigoLista, $inactivos, $alertasFila, $importacion?->id, $importacion?->usuario_id);
                            $despues = $coleccion->get($fila['data']['numero_cliente']);
                            if (! $despues) {
                                throw ValidationException::withMessages(['numero_cliente' => 'La fila no produjo un cliente.']);
                            }
                            if ($historial !== []) {
                                HistorialMontoCliente::insert($historial);
                            }
                            if ($cambio && $importacion) {
                                CambioListaImportacionCliente::create([
                                    'importacion_cliente_id' => $importacion->id, 'numero_cliente' => $cambio['numero_cliente'],
                                    'nombre_cliente' => $cambio['nombre'], 'lista_anterior' => $cambio['lista_anterior'],
                                    'lista_nueva' => $cambio['lista_nueva'], 'tipo_cambio' => $cambio['tipo_cambio'],
                                    'codigo_lista' => $cambio['codigo_lista'], 'monto_nuevo' => $cambio['monto_nuevo'],
                                ]);
                            }
                            $categoria = ! $antes ? 'creados' : ($despues->wasChanged() ? 'actualizados' : 'sin_cambios');
                            $siguiente = $stats;
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
                            // La publicación durable participa del mismo commit que el cliente.
                            app(MobileSyncPublicationService::class)->publishDiffColeccion(
                                collect($antes ? [$antes->id => $antes] : []), collect([$despues->id => $despues]));
                            $this->guardarProgreso($importacion, $siguiente, 'en_curso');

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
                    $this->guardarProgreso($importacion, $stats, 'en_curso');
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

    private function guardarProgreso(?ImportacionCliente $importacion, array $stats, string $estado, ?string $mensaje = null): void
    {
        if (! $importacion) {
            return;
        }
        $revision = $importacion->resumen['revision'] ?? null;
        $conservar = [];
        foreach (['conteos', 'bloqueos', 'avisos', 'muestras', 'inicio_ts'] as $clave) {
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
        if (is_array($revision)) {
            $resumen['revision'] = $revision;
        }
        $importacion->update(['estado' => $estado, 'resumen' => $resumen, 'mensaje_fatal' => $mensaje === null ? null : $this->sanitizarTextoImportacion($mensaje),
            'filas_leidas' => $stats['leidas'], 'filas_procesadas' => $stats['procesadas'], 'filas_omitidas' => $stats['omitidas'],
            'errores' => $stats['errores'], 'ascensos' => $stats['ascensos'], 'descensos' => $stats['descensos'],
            'clientes_marcados_inactivos' => $stats['clientes_marcados_inactivos'], 'duracion_seg' => $stats['duracion_seg'] ?? null]);
        $importacion->resumen = $resumen;
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
            'modalidad' => (string) ($attrs['modalidad_comercial'] ?? ''),
            'origen' => (string) ($attrs['modalidad_origen'] ?? ''),
            'conversion_solicitud_id' => (string) ($attrs['conversion_solicitud_id'] ?? ''),
        ];
        if ($actual != $esperado) {
            throw ValidationException::withMessages(['numero_cliente' => 'El cliente cambió después de la revisión.']);
        }
    }
}
