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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImportarClientesWizerpService
{
    public function __construct(protected ProcesarFilaClienteAction $procesador) {}

    public function ejecutar(UploadedFile $archivo, ?ImportacionCliente $importacion = null): array
    {
        $inicio = microtime(true);
        $stats = array_fill_keys(['leidas', 'procesadas', 'creados', 'actualizados', 'sin_cambios', 'omitidas', 'errores',
            'ascensos', 'descensos', 'activados', 'clientes_marcados_inactivos', 'cambios_lista'], 0);
        $reporteAscensos = [];
        $errores = [];
        $alertas = [];
        $mysql = DB::connection()->getDriverName() === 'mysql';
        $lock = null;
        $adquirido = false;
        try {
            if ($mysql) {
                $adquirido = (int) DB::selectOne("SELECT GET_LOCK('gelia.importacion.clientes.wizerp', 0) AS adquirido")->adquirido === 1;
            } else {
                $lock = Cache::lock('gelia.importacion.clientes.wizerp', 0);
                $adquirido = $lock->get();
            }
            if (! $adquirido) {
                throw ValidationException::withMessages(['archivo' => 'Ya existe una importación de clientes en curso.']);
            }
            $path = $importacion
                ? Storage::disk('local')->path($importacion->ruta_almacenamiento)
                : $archivo->getRealPath();
            // Preflight completo: ningún catálogo ni cliente se escribe antes de validar estructura.
            $validado = app(ValidarArchivoClientesWizerp::class)->leer($path);
            $stats['leidas'] = count($validado['filas']);
            $importaCodigoLista = in_array('codigo_lista', $validado['headers'], true);
            $this->sincronizarListasWizerp();
            $mapa = $this->cargarMapaVendedoras();
            $listas = CatalogoListaDescuento::orderByDesc('monto_requerido')->get();
            Cache::put('import_clientes_en_curso', true, now()->addHours(2));
            foreach ($validado['filas'] as $fila) {
                if ($fila['duplicada']) {
                    $stats['omitidas']++;
                    $this->guardarProgreso($importacion, $stats, 'en_curso');

                    continue;
                }
                try {
                    $resultado = DB::transaction(function () use ($fila, $listas, $mapa, $importaCodigoLista, $importacion, $stats) {
                        $cliente = Cliente::with('facturaCobranzaActiva')->where('numero_cliente', $fila['data']['numero_cliente'])->lockForUpdate()->first();
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
                        $reporteAscensos[] = $resultado['cambio'];
                    }
                    $alertas = array_merge($alertas, $resultado['alertas']);
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
            $estado = $stats['errores'] === 0 ? 'completo' : ($stats['procesadas'] > 0 ? 'parcial' : 'fallido');
            $stats['duracion_seg'] = round(microtime(true) - $inicio, 2);
            $this->guardarProgreso($importacion, $stats, $estado);
        } catch (\Throwable $e) {
            $stats['no_aplicadas'] = max(0, $stats['leidas'] - $stats['procesadas'] - $stats['omitidas'] - $stats['errores']);
            if ($importacion) {
                $this->guardarProgreso($importacion, $stats, $stats['procesadas'] > 0 ? 'parcial' : 'fallido', $e->getMessage());
            }
            throw $e;
        } finally {
            if ($adquirido) {
                Cache::forget('import_clientes_en_curso');
                if ($mysql) {
                    DB::selectOne("SELECT RELEASE_LOCK('gelia.importacion.clientes.wizerp')");
                } else {
                    $lock->release();
                }
            }
        }
        if ($alertas !== []) {
            Notification::send(User::permission('cobranza.recibir_alertas')->get(), new AlertaLimiteCreditoSuperadoMasivoNotification($alertas));
        }

        return ['ascensos' => $reporteAscensos, 'clientes_marcados_inactivos' => $stats['clientes_marcados_inactivos'],
            'stats' => $stats, 'estado' => $estado, 'errores_detalle' => array_slice($errores, 0, 100), 'detalle_limitado' => count($errores) > 100];
    }

    private function guardarProgreso(?ImportacionCliente $importacion, array $stats, string $estado, ?string $mensaje = null): void
    {
        $importacion?->update(['estado' => $estado, 'resumen' => $stats, 'mensaje_fatal' => $mensaje === null ? null : $this->sanitizarTextoImportacion($mensaje),
            'filas_leidas' => $stats['leidas'], 'filas_procesadas' => $stats['procesadas'], 'filas_omitidas' => $stats['omitidas'],
            'errores' => $stats['errores'], 'ascensos' => $stats['ascensos'], 'descensos' => $stats['descensos'],
            'clientes_marcados_inactivos' => $stats['clientes_marcados_inactivos'], 'duracion_seg' => $stats['duracion_seg'] ?? null]);
    }

    private function sanitizarTextoImportacion(?string $texto): string
    {
        return mb_substr(mb_scrub($texto ?? '', 'UTF-8'), 0, 4000);
    }

    private function cargarMapaVendedoras(): array
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

    private function sincronizarListasWizerp(): void
    {
        $listasOficiales = [
            ['nombre' => 'MAYOREO DIAMANTE', 'monto_requerido' => 80001.00],
            ['nombre' => 'MAYOREO ORO',      'monto_requerido' => 30001.00],
            ['nombre' => 'MAYOREO PLATA',    'monto_requerido' => 5001.00],
            ['nombre' => 'MAYOREO BRONCE',   'monto_requerido' => 0.01],
            ['nombre' => 'PUBLICO GENERAL',  'monto_requerido' => 0.00],
            ['nombre' => 'PLATAFORMAS',      'monto_requerido' => 999999.00],
            ['nombre' => 'COLABORADORES',    'monto_requerido' => 999999.00],
        ];

        foreach ($listasOficiales as $lista) {
            CatalogoListaDescuento::firstOrCreate(
                ['nombre' => $lista['nombre']],
                ['monto_requerido' => $lista['monto_requerido'], 'activo' => 1]
            );
        }
    }
}
