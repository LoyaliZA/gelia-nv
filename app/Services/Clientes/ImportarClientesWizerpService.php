<?php

namespace App\Services\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Exception;

class ImportarClientesWizerpService
{
    // --- PROPIEDADES ---
    protected ProcesarFilaClienteAction $procesador;

    // --- CONSTRUCTOR ---
    public function __construct(ProcesarFilaClienteAction $procesador)
    {
        $this->procesador = $procesador;
    }

    // --- METODOS PRINCIPALES ---
    public function ejecutar(UploadedFile $archivo): array
    {
        $path = $archivo->getRealPath();
        $file = fopen($path, 'r');
        
        $headers = $this->procesarCabeceras(fgetcsv($file));
        $this->sincronizarListasWizerp();
        
        $mapaVendedoras = $this->cargarMapaVendedoras();
        $listas = CatalogoListaDescuento::orderBy('monto_requerido', 'desc')->get();
        $reporteAscensos = [];

        DB::transaction(function () use ($file, $headers, $listas, $mapaVendedoras, &$reporteAscensos) {
            $numeroFila = 1;

            while ($row = fgetcsv($file)) {
                $numeroFila++;
                
                if (empty(array_filter($row))) {
                    continue;
                }
                
                // Normalizar la fila para evitar disparidad con las cabeceras (Previene omisiones silenciosas)
                $rowNormalizado = $this->alinearFilaConCabeceras($row, $headers);
                $data = array_combine($headers, $rowNormalizado);

                if (empty(trim($data['numero_cliente'] ?? ''))) {
                    Log::warning("Importacion Masiva - Fila {$numeroFila} omitida: Sin numero_cliente.");
                    continue;
                }

                try {
                    $cambio = $this->procesador->ejecutar($data, $listas, $mapaVendedoras);
                    if ($cambio) {
                        $reporteAscensos[] = $cambio;
                    }
                } catch (Exception $e) {
                    // Evita que un error en un cliente rompa la importación completa
                    Log::error("Importacion Masiva - Error en cliente {$data['numero_cliente']}: " . $e->getMessage());
                }
            }
        });

        fclose($file);
        return $reporteAscensos;
    }

    // --- UTILERIAS DE ARCHIVO ---
    private function procesarCabeceras(array $headersRaw): array
    {
        $headersRaw[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headersRaw[0]); 
        return array_map(function($header) {
            return strtolower(trim($header)); 
        }, $headersRaw);
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

    // --- CARGA DE DATOS ---
    private function cargarMapaVendedoras(): array
    {
        $mapa = [];
        $vendedoras = User::whereHas('roles', function($q) {
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