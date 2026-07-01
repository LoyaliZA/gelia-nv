<?php

namespace App\Services\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\CambioListaImportacionCliente;
use App\Models\Cliente;
use App\Models\ErroresImportacionCliente;
use App\Models\HistorialMontoCliente;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AlertaLimiteCreditoSuperadoMasivoNotification;

class ImportarClientesWizerpService
{
    private const CHUNK_SIZE = 500;

    protected ProcesarFilaClienteAction $procesador;

    public function __construct(ProcesarFilaClienteAction $procesador)
    {
        $this->procesador = $procesador;
    }

    public function ejecutar(UploadedFile $archivo, ?\App\Models\ImportacionCliente $importacion = null): array
    {
        $inicio = microtime(true);
        $importacionClienteId = $importacion?->id;
        $usuarioId = $importacion?->usuario_id;
        $path = $importacion
            ? \Illuminate\Support\Facades\Storage::disk('local')->path($importacion->ruta_almacenamiento)
            : $archivo->getRealPath();
        $file = fopen($path, 'r');

        $headers = $this->procesarCabeceras(fgetcsv($file));
        $importaCodigoLista = in_array('codigo_lista', $headers, true);
        $this->sincronizarListasWizerp();

        $mapaVendedoras = $this->cargarMapaVendedoras();
        $listas = CatalogoListaDescuento::orderBy('monto_requerido', 'desc')->get();
        $reporteAscensos = [];
        $marcadosInactivos = 0;
        $filas = [];
        $erroresBatch = [];
        $cambiosListaBatch = [];
        $stats = [
            'leidas'     => 0,
            'procesadas' => 0,
            'omitidas'   => 0,
            'errores'    => 0,
            'ascensos'   => 0,
            'descensos'  => 0,
        ];

        $numeroFila = 1;
        while ($row = fgetcsv($file)) {
            $numeroFila++;

            if (empty(array_filter($row))) {
                continue;
            }

            $stats['leidas']++;
            $rowNormalizado = $this->alinearFilaConCabeceras($row, $headers);
            $data = array_combine($headers, $rowNormalizado);
            $data = $this->normalizarClavesFila($data);

            if (empty(trim($data['numero_cliente'] ?? ''))) {
                $stats['omitidas']++;
                Log::warning("Importacion Masiva - Fila {$numeroFila} omitida: Sin numero_cliente.");
                continue;
            }

            $filas[] = [
                'data' => $data,
                'numero_fila' => $numeroFila,
            ];
        }

        fclose($file);

        Cache::put('import_clientes_en_curso', true, now()->addHours(2));

        $alertasLimiteExcedido = [];

        try {
            foreach (array_chunk($filas, self::CHUNK_SIZE) as $chunk) {
                DB::transaction(function () use ($chunk, $listas, $mapaVendedoras, $importaCodigoLista, &$reporteAscensos, &$marcadosInactivos, &$stats, &$alertasLimiteExcedido, &$erroresBatch, &$cambiosListaBatch, $importacionClienteId, $usuarioId) {
                    $historialBatch = [];
                    $numeros = array_values(array_unique(array_map(
                        fn (array $fila) => trim($fila['data']['numero_cliente']),
                        $chunk
                    )));

                    $clientesPorNumero = Cliente::with('facturaCobranzaActiva')
                        ->whereIn('numero_cliente', $numeros)
                        ->get()
                        ->keyBy('numero_cliente');

                    foreach ($chunk as $fila) {
                        $data = $fila['data'];
                        $numeroFilaCsv = $fila['numero_fila'];

                        try {
                            $cambio = $this->procesador->ejecutar(
                                $data,
                                $listas,
                                $mapaVendedoras,
                                $clientesPorNumero,
                                $historialBatch,
                                $importaCodigoLista,
                                $marcadosInactivos,
                                $alertasLimiteExcedido,
                                $importacionClienteId,
                                $usuarioId,
                            );
                            $stats['procesadas']++;
                            if ($cambio) {
                                if (($cambio['tipo_cambio'] ?? null) === CambioListaImportacionCliente::TIPO_ASCENSO) {
                                    $reporteAscensos[] = $cambio;
                                    $stats['ascensos']++;
                                } elseif (($cambio['tipo_cambio'] ?? null) === CambioListaImportacionCliente::TIPO_DESCENSO) {
                                    $stats['descensos']++;
                                }

                                if ($importacionClienteId !== null) {
                                    $ahora = now();
                                    $cambiosListaBatch[] = [
                                        'importacion_cliente_id' => $importacionClienteId,
                                        'numero_cliente' => $cambio['numero_cliente'],
                                        'nombre_cliente' => $this->sanitizarTextoImportacion($cambio['nombre'] ?? null) ?: null,
                                        'lista_anterior' => $cambio['lista_anterior'],
                                        'lista_nueva' => $cambio['lista_nueva'],
                                        'tipo_cambio' => $cambio['tipo_cambio'],
                                        'codigo_lista' => $cambio['codigo_lista'] !== '' ? $cambio['codigo_lista'] : null,
                                        'monto_nuevo' => $cambio['monto_nuevo'] ?? null,
                                        'created_at' => $ahora,
                                        'updated_at' => $ahora,
                                    ];
                                }
                            }
                        } catch (Exception $e) {
                            $stats['errores']++;
                            Log::error("Importacion Masiva - Error en cliente {$data['numero_cliente']}: " . $e->getMessage());

                            if ($importacionClienteId !== null) {
                                $ahora = now();
                                $erroresBatch[] = [
                                    'importacion_cliente_id' => $importacionClienteId,
                                    'numero_fila' => $numeroFilaCsv,
                                    'numero_cliente' => trim($data['numero_cliente'] ?? '') ?: null,
                                    'mensaje' => $this->sanitizarTextoImportacion($e->getMessage()),
                                    'created_at' => $ahora,
                                    'updated_at' => $ahora,
                                ];
                            }
                        }
                    }

                    if (!empty($historialBatch)) {
                        foreach (array_chunk($historialBatch, 500) as $loteHistorial) {
                            HistorialMontoCliente::insert($loteHistorial);
                        }
                    }
                });
            }
        } finally {
            Cache::forget('import_clientes_en_curso');
        }

        if ($importacionClienteId !== null && ! empty($cambiosListaBatch)) {
            try {
                foreach (array_chunk($cambiosListaBatch, 500) as $loteCambiosLista) {
                    CambioListaImportacionCliente::insert($loteCambiosLista);
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudieron persistir cambios de lista de importación', [
                    'importacion_cliente_id' => $importacionClienteId,
                    'cambios_count' => count($cambiosListaBatch),
                    'error' => $this->sanitizarTextoImportacion($e->getMessage()),
                ]);
            }
        }

        if ($importacionClienteId !== null && ! empty($erroresBatch)) {
            try {
                foreach (array_chunk($erroresBatch, 500) as $loteErrores) {
                    ErroresImportacionCliente::insert($loteErrores);
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudieron persistir detalles de errores de importación', [
                    'importacion_cliente_id' => $importacionClienteId,
                    'errores_count' => count($erroresBatch),
                    'error' => $this->sanitizarTextoImportacion($e->getMessage()),
                ]);
            }
        }

        if (!empty($alertasLimiteExcedido)) {
            $usuariosNotificar = User::permission('cobranza.recibir_alertas')->get();
            if ($usuariosNotificar->isNotEmpty()) {
                Notification::send($usuariosNotificar, new AlertaLimiteCreditoSuperadoMasivoNotification($alertasLimiteExcedido));
            }
        }

        $duracion = round(microtime(true) - $inicio, 2);
        Log::info('Importacion clientes Wizerp finalizada', [
            'filas_leidas'                => $stats['leidas'],
            'filas_procesadas'            => $stats['procesadas'],
            'filas_omitidas'              => $stats['omitidas'],
            'errores'                     => $stats['errores'],
            'ascensos'                    => $stats['ascensos'],
            'descensos'                   => $stats['descensos'],
            'clientes_marcados_inactivos' => $marcadosInactivos,
            'duracion_seg'                => $duracion,
            'memoria_pico_mb'             => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);

        return [
            'ascensos'                    => $reporteAscensos,
            'clientes_marcados_inactivos' => $marcadosInactivos,
            'stats'                       => array_merge($stats, [
                'ascensos'                    => $stats['ascensos'],
                'descensos'                   => $stats['descensos'],
                'clientes_marcados_inactivos' => $marcadosInactivos,
                'duracion_seg'                => $duracion,
            ]),
        ];
    }

    private function sanitizarTextoImportacion(?string $texto): string
    {
        if ($texto === null || $texto === '') {
            return '';
        }

        if (function_exists('mb_scrub')) {
            return mb_substr(mb_scrub($texto, 'UTF-8'), 0, 4000);
        }

        $limpio = @iconv('UTF-8', 'UTF-8//IGNORE', $texto);

        return mb_substr($limpio !== false ? $limpio : '', 0, 4000);
    }

    private function procesarCabeceras(array $headersRaw): array
    {
        $headersRaw[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headersRaw[0]);

        $headers = array_map(function ($header) {
            return $this->normalizarNombreCabecera(strtolower(trim($header)));
        }, $headersRaw);

        return $headers;
    }

    private function normalizarNombreCabecera(string $header): string
    {
        $alias = [
            'numero_cli'        => 'numero_cliente',
            'monto_venta_actua' => 'monto_venta_actual',
            'limite_asignado'   => 'monto_credito_autorizado',
            'limite_de_credito' => 'monto_credito_autorizado',
            'dias_de_credito'   => 'dias_credito',
            'correo'            => 'correo_electronico',
            'email'             => 'correo_electronico',
            'cp'                => 'codigo_postal',
            'razon_social'      => 'nombre_razon_social',
        ];

        return $alias[$header] ?? $header;
    }

    private function normalizarClavesFila(array $data): array
    {
        $alias = [
            'numero_cli'        => 'numero_cliente',
            'monto_venta_actua' => 'monto_venta_actual',
            'limite_asignado'   => 'monto_credito_autorizado',
            'limite_de_credito' => 'monto_credito_autorizado',
            'dias_de_credito'   => 'dias_credito',
            'correo'            => 'correo_electronico',
            'email'             => 'correo_electronico',
            'cp'                => 'codigo_postal',
            'razon_social'      => 'nombre_razon_social',
        ];

        foreach ($alias as $from => $to) {
            if (!isset($data[$to]) && isset($data[$from])) {
                $data[$to] = $data[$from];
            }
        }

        return $data;
    }

    private function alinearFilaConCabeceras(array $row, array $headers): array
    {
        $totalHeaders = count($headers);
        $totalRow = count($row);

        if ($totalRow < $totalHeaders) {
            return array_pad($row, $totalHeaders, '');
        }

        if ($totalRow > $totalHeaders) {
            return array_slice($row, 0, $totalHeaders);
        }

        return $row;
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
