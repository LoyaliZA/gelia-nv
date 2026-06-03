<?php

namespace App\Services\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\HistorialMontoCliente;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportarClientesWizerpService
{
    private const CHUNK_SIZE = 500;

    protected ProcesarFilaClienteAction $procesador;

    public function __construct(ProcesarFilaClienteAction $procesador)
    {
        $this->procesador = $procesador;
    }

    public function ejecutar(UploadedFile $archivo): array
    {
        $inicio = microtime(true);
        $path = $archivo->getRealPath();
        $file = fopen($path, 'r');

        $headers = $this->procesarCabeceras(fgetcsv($file));
        $importaCodigoLista = in_array('codigo_lista', $headers, true);
        $this->sincronizarListasWizerp();

        $mapaVendedoras = $this->cargarMapaVendedoras();
        $listas = CatalogoListaDescuento::orderBy('monto_requerido', 'desc')->get();
        $reporteAscensos = [];
        $marcadosInactivos = 0;
        $filas = [];
        $stats = [
            'leidas'     => 0,
            'procesadas' => 0,
            'omitidas'   => 0,
            'errores'    => 0,
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

            $filas[] = $data;
        }

        fclose($file);

        Cache::put('import_clientes_en_curso', true, now()->addHours(2));

        try {
            foreach (array_chunk($filas, self::CHUNK_SIZE) as $chunk) {
                DB::transaction(function () use ($chunk, $listas, $mapaVendedoras, $importaCodigoLista, &$reporteAscensos, &$marcadosInactivos, &$stats) {
                    $historialBatch = [];
                    $numeros = array_values(array_unique(array_map(
                        fn (array $fila) => trim($fila['numero_cliente']),
                        $chunk
                    )));

                    $clientesPorNumero = Cliente::whereIn('numero_cliente', $numeros)
                        ->get()
                        ->keyBy('numero_cliente');

                    foreach ($chunk as $data) {
                        try {
                            $cambio = $this->procesador->ejecutar(
                                $data,
                                $listas,
                                $mapaVendedoras,
                                $clientesPorNumero,
                                $historialBatch,
                                $importaCodigoLista,
                                $marcadosInactivos
                            );
                            $stats['procesadas']++;
                            if ($cambio) {
                                $reporteAscensos[] = $cambio;
                            }
                        } catch (Exception $e) {
                            $stats['errores']++;
                            Log::error("Importacion Masiva - Error en cliente {$data['numero_cliente']}: " . $e->getMessage());
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

        $duracion = round(microtime(true) - $inicio, 2);
        Log::info('Importacion clientes Wizerp finalizada', [
            'filas_leidas'     => $stats['leidas'],
            'filas_procesadas' => $stats['procesadas'],
            'filas_omitidas'   => $stats['omitidas'],
            'errores'          => $stats['errores'],
            'ascensos'                    => count($reporteAscensos),
            'clientes_marcados_inactivos' => $marcadosInactivos,
            'duracion_seg'                => $duracion,
            'memoria_pico_mb'             => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);

        return [
            'ascensos'                    => $reporteAscensos,
            'clientes_marcados_inactivos' => $marcadosInactivos,
        ];
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
        ];

        return $alias[$header] ?? $header;
    }

    private function normalizarClavesFila(array $data): array
    {
        $alias = [
            'numero_cli'        => 'numero_cliente',
            'monto_venta_actua' => 'monto_venta_actual',
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
            ['nombre' => 'MAYOREO DIAMANTE', 'monto_requerido' => 85108.00],
            ['nombre' => 'MAYOREO ORO',      'monto_requerido' => 31250.00],
            ['nombre' => 'MAYOREO PLATA',    'monto_requerido' => 5104.00],
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
