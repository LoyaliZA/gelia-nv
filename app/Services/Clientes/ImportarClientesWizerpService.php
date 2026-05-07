<?php

namespace App\Services\Clientes;

use App\Models\Cliente;
use App\Models\CatalogoListaDescuento;
use App\Models\HistorialMontoCliente;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;

class ImportarClientesWizerpService
{
    // Diccionario para traducir los códigos de Wizerp a las listas de Gelia
    protected $mapaWizerp = [
        'PG' => 'PUBLICO GENERAL',
        '7'  => 'COLABORADORES',
        '4'  => 'MAYOREO DIAMANTE',
        '3'  => 'MAYOREO PLATA',
        '2'  => 'MAYOREO BRONCE',
        '1'  => 'MAYOREO ORO',
    ];

    // Diccionario en memoria para las vendedoras
    protected $mapaVendedoras = [];

    public function ejecutar(UploadedFile $archivo): void
    {
        $path = $archivo->getRealPath();
        $file = fopen($path, 'r');
        
        $headers = fgetcsv($file); 
        $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]); 
        
        $headers = array_map(function($header) {
            return strtolower(trim($header)); 
        }, $headers);

        // 1. Sincronizamos catálogos y cargamos diccionarios
        $this->sincronizarListasWizerp();
        $this->cargarMapaVendedoras();

        $listas = CatalogoListaDescuento::orderBy('monto_requerido', 'desc')->get();

        DB::beginTransaction();
        try {
            while ($row = fgetcsv($file)) {
                if (empty(array_filter($row)) || count($headers) !== count($row)) continue;
                
                $data = array_combine($headers, $row);

                if (!isset($data['numero_cliente']) || empty(trim($data['numero_cliente']))) continue;

                $numeroCliente = trim($data['numero_cliente']);
                $cliente = Cliente::where('numero_cliente', $numeroCliente)->first();
                
                if ($cliente) {
                    $this->actualizarClienteExistente($cliente, $data, $listas);
                } else {
                    $this->crearNuevoCliente($data, $listas);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } finally {
            fclose($file);
        }
    }

    // --- MÉTODOS DE SOPORTE Y TRADUCCIÓN ---

    private function cargarMapaVendedoras(): void
    {
        // Traemos solo a los usuarios con rol de Vendedor
        $vendedoras = User::whereHas('roles', function($q) {
            $q->where('name', 'Vendedor');
        })->get(['id', 'name', 'username']);

        // Mapeamos en mayúsculas para evitar errores de tipeo
        foreach ($vendedoras as $vendedora) {
            $this->mapaVendedoras[strtoupper(trim($vendedora->name))] = $vendedora->id;
            
            // También mapeamos por username por si deciden usar nombres cortos como "FANY"
            if ($vendedora->username) {
                $this->mapaVendedoras[strtoupper(trim($vendedora->username))] = $vendedora->id;
            }
        }
    }

    private function traducirVendedora($nombreWizerp): ?int
    {
        if (empty(trim($nombreWizerp))) return null;
        $nombreNormalizado = strtoupper(trim($nombreWizerp));
        return $this->mapaVendedoras[$nombreNormalizado] ?? null;
    }

    private function sincronizarListasWizerp(): void
    {
        $listasOficiales = [
            ['nombre' => 'MAYOREO DIAMANTE', 'monto_requerido' => 80000.00],
            ['nombre' => 'MAYOREO ORO', 'monto_requerido' => 30000.00],
            ['nombre' => 'MAYOREO PLATA', 'monto_requerido' => 5000.00],
            ['nombre' => 'MAYOREO BRONCE', 'monto_requerido' => 0.01], 
            ['nombre' => 'PUBLICO GENERAL', 'monto_requerido' => 0.00],
            ['nombre' => 'COLABORADORES', 'monto_requerido' => 999999.00], 
        ];

        foreach ($listasOficiales as $lista) {
            CatalogoListaDescuento::updateOrCreate(
                ['nombre' => $lista['nombre']],
                ['monto_requerido' => $lista['monto_requerido'], 'activo' => 1]
            );
        }
    }

    // --- LÓGICA DE INSERCIÓN Y ACTUALIZACIÓN ---

    private function crearNuevoCliente(array $data, $listas): void
    {
        if (!isset($data['nombre']) || empty(trim($data['nombre']))) return; 

        $clienteNuevo = [
            'numero_cliente' => trim($data['numero_cliente']),
            'nombre' => trim($data['nombre']),
            'monto_venta_actual' => 0.00,
            'es_heredado' => false
        ];

        // Traducimos el TAG de Wizerp (ej. "FANY") a un ID de usuario de Gelia
        if (isset($data['vendedor_id'])) {
            $clienteNuevo['vendedor_id'] = $this->traducirVendedora($data['vendedor_id']);
        }

        $listaId = null;
        if (array_key_exists('codigo_lista', $data)) {
            $codigo = strtoupper(trim($data['codigo_lista']));
            if ($codigo === '') {
                $codigo = 'PG';
            }

            if (isset($this->mapaWizerp[$codigo])) {
                $listaObj = $listas->where('nombre', $this->mapaWizerp[$codigo])->first();
                $listaId = $listaObj->id ?? null;
            }
        }

        if (isset($data['monto_venta_actual']) && is_numeric($data['monto_venta_actual'])) {
            $clienteNuevo['monto_venta_actual'] = (float) $data['monto_venta_actual'];
            if (!$listaId) {
                $listaId = $this->determinarListaPorMonto($clienteNuevo['monto_venta_actual'], $listas);
            }
        }

        $clienteNuevo['lista_actual_id'] = $listaId ?? $listas->where('nombre', 'PUBLICO GENERAL')->first()->id;

        Cliente::create($clienteNuevo);
    }

    private function actualizarClienteExistente(Cliente $cliente, array $data, $listas): void
    {
        $updateData = [];
        
        if (isset($data['nombre']) && !empty(trim($data['nombre']))) {
            $updateData['nombre'] = trim($data['nombre']);
        }

        // Traducimos el TAG de Wizerp (ej. "FANY") a un ID de usuario de Gelia
        if (isset($data['vendedor_id'])) {
            $vendedorIdTraducido = $this->traducirVendedora($data['vendedor_id']);
            if ($vendedorIdTraducido) {
                $updateData['vendedor_id'] = $vendedorIdTraducido;
            }
        }

        if (array_key_exists('codigo_lista', $data)) {
            $codigo = strtoupper(trim($data['codigo_lista']));
            if ($codigo === '') {
                $codigo = 'PG';
            }

            if (isset($this->mapaWizerp[$codigo])) {
                $listaObj = $listas->where('nombre', $this->mapaWizerp[$codigo])->first();
                if ($listaObj && $cliente->lista_actual_id !== $listaObj->id) {
                    $updateData['lista_actual_id'] = $listaObj->id;
                }
            }
        }
        
        if (isset($data['monto_venta_actual']) && is_numeric($data['monto_venta_actual'])) {
            $montoNuevo = (float) $data['monto_venta_actual'];
            
            if ($cliente->monto_venta_actual != $montoNuevo) {
                $diferencia = $montoNuevo - $cliente->monto_venta_actual;
                
                if (!isset($updateData['lista_actual_id'])) {
                    $updateData['lista_actual_id'] = $this->determinarListaPorMonto($montoNuevo, $listas);
                }

                HistorialMontoCliente::create([
                    'cliente_id' => $cliente->id,
                    'monto_anterior' => $cliente->monto_venta_actual,
                    'monto_nuevo' => $montoNuevo,
                    'diferencia_aplicada' => $diferencia
                ]);

                $updateData['monto_venta_actual'] = $montoNuevo;
            }
        }
        
        if (!empty($updateData)) {
            $cliente->update($updateData);
        }
    }

    private function determinarListaPorMonto(float $monto, $listas)
    {
        foreach ($listas as $lista) {
            if ($lista->nombre === 'COLABORADORES') continue;
            if ($monto >= $lista->monto_requerido) {
                return $lista->id;
            }
        }
        return $listas->where('nombre', 'PUBLICO GENERAL')->first()->id; 
    }
}