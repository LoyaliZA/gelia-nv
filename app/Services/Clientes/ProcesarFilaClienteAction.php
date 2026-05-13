<?php

namespace App\Services\Clientes;

use App\Models\Cliente;
use App\Models\HistorialMontoCliente;

class ProcesarFilaClienteAction
{
    protected $mapaWizerp = [
        'PG' => 'PUBLICO GENERAL',
        '7'  => 'COLABORADORES',
        '4'  => 'MAYOREO DIAMANTE',
        '3'  => 'MAYOREO PLATA',
        '2'  => 'MAYOREO BRONCE',
        '1'  => 'MAYOREO ORO',
    ];

    public function ejecutar(array $data, $listas, array $mapaVendedoras): void
    {
        $numeroCliente = trim($data['numero_cliente']);
        $cliente = Cliente::where('numero_cliente', $numeroCliente)->first();
        
        if ($cliente) {
            $this->actualizarClienteExistente($cliente, $data, $listas, $mapaVendedoras);
        } else {
            $this->crearNuevoCliente($data, $listas, $mapaVendedoras);
        }
    }

    // --- CORRECCIÓN: Función auxiliar para evaluar el "SI" de Excel ---
    private function evaluarHeredado(?string $valor): bool
    {
        if (!$valor) return false;
        $limpio = strtoupper(trim($valor));
        return in_array($limpio, ['1', 'SI', 'SÍ', 'TRUE', 'VERDADERO', 'X', 'YES']);
    }

    private function traducirVendedora(?string $nombreWizerp, array $mapaVendedoras): ?int
    {
        if (empty(trim($nombreWizerp ?? ''))) return null;
        $nombreNormalizado = strtoupper(trim($nombreWizerp));
        return $mapaVendedoras[$nombreNormalizado] ?? null;
    }

    private function crearNuevoCliente(array $data, $listas, array $mapaVendedoras): void
    {
        if (!isset($data['nombre']) || empty(trim($data['nombre']))) return;

        $clienteNuevo = [
            'numero_cliente' => trim($data['numero_cliente']),
            'nombre' => trim($data['nombre']),
            'monto_venta_actual' => 0.00,
            'es_heredado' => $this->evaluarHeredado($data['es_heredado'] ?? null)
        ];

        if (!empty($data['vendedor_id'])) {
            $clienteNuevo['vendedor_id'] = $this->traducirVendedora($data['vendedor_id'], $mapaVendedoras);
        }

        $listaId = $this->resolverLista($data, $listas);
        $montoExtraido = $this->limpiarMonto($data['monto_venta_actual'] ?? null);

        if ($montoExtraido !== null) {
            $clienteNuevo['monto_venta_actual'] = $montoExtraido;
            if (!$listaId) {
                $listaId = $this->determinarListaPorMonto($montoExtraido, $listas);
            }
        }

        $clienteNuevo['lista_actual_id'] = $listaId ?? $listas->where('nombre', 'PUBLICO GENERAL')->first()->id;

        Cliente::create($clienteNuevo);
    }

    private function actualizarClienteExistente(Cliente $cliente, array $data, $listas, array $mapaVendedoras): void
    {
        $updateData = [];
        
        if (isset($data['nombre']) && !empty(trim($data['nombre']))) {
            $updateData['nombre'] = trim($data['nombre']);
        }

        if (!empty($data['vendedor_id'])) {
            $vendedorIdTraducido = $this->traducirVendedora($data['vendedor_id'], $mapaVendedoras);
            if ($vendedorIdTraducido) $updateData['vendedor_id'] = $vendedorIdTraducido;
        }

        // Integración del booleano seguro
        if (isset($data['es_heredado']) && $data['es_heredado'] !== '') {
            $esHeredadoCsv = $this->evaluarHeredado($data['es_heredado']);
            if ($cliente->es_heredado !== $esHeredadoCsv) {
                $updateData['es_heredado'] = $esHeredadoCsv;
            }
        }

        $listaId = $this->resolverLista($data, $listas);
        if ($listaId && $cliente->lista_actual_id !== $listaId) {
            $updateData['lista_actual_id'] = $listaId;
        }
        
        $montoExtraido = $this->limpiarMonto($data['monto_venta_actual'] ?? null);
        if ($montoExtraido !== null && $cliente->monto_venta_actual != $montoExtraido) {
            
            if (!isset($updateData['lista_actual_id'])) {
                $updateData['lista_actual_id'] = $this->determinarListaPorMonto($montoExtraido, $listas);
            }

            HistorialMontoCliente::create([
                'cliente_id' => $cliente->id,
                'monto_anterior' => $cliente->monto_venta_actual,
                'monto_nuevo' => $montoExtraido,
                'diferencia_aplicada' => $montoExtraido - $cliente->monto_venta_actual
            ]);

            $updateData['monto_venta_actual'] = $montoExtraido;
        }
        
        if (!empty($updateData)) {
            $cliente->update($updateData);
        }
    }

    // --- UTILERÍAS ---
    private function resolverLista(array $data, $listas): ?int
    {
        if (!array_key_exists('codigo_lista', $data)) return null;
        
        $codigo = strtoupper(trim($data['codigo_lista']));
        $codigo = $codigo === '' ? 'PG' : $codigo;

        if (isset($this->mapaWizerp[$codigo])) {
            return $listas->where('nombre', $this->mapaWizerp[$codigo])->first()->id ?? null;
        }
        return null;
    }

    private function limpiarMonto(?string $montoRaw): ?float
    {
        if ($montoRaw === null) return null;
        $montoLimpio = trim(str_replace(['$', ','], '', $montoRaw));
        if ($montoLimpio === '-') return 0.00;
        return is_numeric($montoLimpio) ? (float) $montoLimpio : null;
    }

    private function determinarListaPorMonto(float $monto, $listas): int
    {
        foreach ($listas as $lista) {
            if ($lista->nombre === 'COLABORADORES') continue;
            if ($monto >= $lista->monto_requerido) return $lista->id;
        }
        return $listas->where('nombre', 'PUBLICO GENERAL')->first()->id; 
    }
}