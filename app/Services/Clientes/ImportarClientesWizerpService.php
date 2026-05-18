<?php

namespace App\Services\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use App\Services\Clientes\ProcesarFilaClienteAction;

class ImportarClientesWizerpService
{
    protected ProcesarFilaClienteAction $procesador;

    // Inyectamos la acción para delegar la responsabilidad
    public function __construct(ProcesarFilaClienteAction $procesador)
    {
        $this->procesador = $procesador;
    }

    public function ejecutar(UploadedFile $archivo): array
    {
        $path = $archivo->getRealPath();
        $file = fopen($path, 'r');
        
        $headers = fgetcsv($file); 
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]); 
        
        $headers = array_map(function($header) {
            return strtolower(trim($header)); 
        }, $headers);

        $this->sincronizarListasWizerp();
        $mapaVendedoras = $this->cargarMapaVendedoras();
        $listas = CatalogoListaDescuento::orderBy('monto_requerido', 'desc')->get();

        $reporteAscensos = [];

        DB::transaction(function () use ($file, $headers, $listas, $mapaVendedoras, &$reporteAscensos) {
            while ($row = fgetcsv($file)) {
                if (empty(array_filter($row)) || count($headers) !== count($row)) continue;
                
                $data = array_combine($headers, $row);

                if (!isset($data['numero_cliente']) || empty(trim($data['numero_cliente']))) continue;

                $cambio = $this->procesador->ejecutar($data, $listas, $mapaVendedoras);
                
                if ($cambio) {
                    $reporteAscensos[] = $cambio;
                }
            }
        });

        fclose($file);
        
        return $reporteAscensos;
    }

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
            ['nombre' => 'MAYOREO DIAMANTE', 'monto_requerido' => 80000.00],
            ['nombre' => 'MAYOREO ORO', 'monto_requerido' => 30000.00],
            ['nombre' => 'MAYOREO PLATA', 'monto_requerido' => 5000.00],
            ['nombre' => 'MAYOREO BRONCE', 'monto_requerido' => 0.01], 
            ['nombre' => 'PUBLICO GENERAL', 'monto_requerido' => 0.00],
            ['nombre' => 'PLATAFORMAS', 'monto_requerido' => 999999.00],
            ['nombre' => 'COLABORADORES', 'monto_requerido' => 999999.00], 
        ];

        foreach ($listasOficiales as $lista) {
            CatalogoListaDescuento::updateOrCreate(
                ['nombre' => $lista['nombre']],
                ['monto_requerido' => $lista['monto_requerido'], 'activo' => 1]
            );
        }
    }
}