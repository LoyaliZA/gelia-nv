<?php

namespace App\Services\Clientes;

use App\Models\Cliente;
use App\Models\HistorialMontoCliente;

class ProcesarFilaClienteAction
{
    // Diccionario extendido para soportar códigos y literales
    protected $mapaWizerp = [
        'PG'       => 'PUBLICO GENERAL',
        '7'        => 'COLABORADORES',
        '5'        => 'PLATAFORMAS',
        '4'        => 'MAYOREO DIAMANTE',
        '3'        => 'MAYOREO PLATA',
        '2'        => 'MAYOREO BRONCE',
        '1'        => 'MAYOREO ORO',
        'DIAMANTE' => 'MAYOREO DIAMANTE',
        'ORO'      => 'MAYOREO ORO',
        'PLATA'    => 'MAYOREO PLATA',
        'BRONCE'   => 'MAYOREO BRONCE',
        'COLABORADORES' => 'COLABORADORES',
        'PLATAFORMAS'   => 'PLATAFORMAS',
        'PUBLICO GENERAL' => 'PUBLICO GENERAL',
    ];

    public function ejecutar(array $data, $listas, array $mapaVendedoras): ?array
    {
        $numeroCliente = trim($data['numero_cliente'] ?? '');
        if (empty($numeroCliente)) return null;

        $cliente = Cliente::where('numero_cliente', $numeroCliente)->first();
        
        if ($cliente) {
            return $this->actualizarClienteExistente($cliente, $data, $listas, $mapaVendedoras);
        } else {
            $this->crearNuevoCliente($data, $listas, $mapaVendedoras);
            return null; 
        }
    }

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

    // Valida exclusión de cálculos generales para listas administrativas
    private function esListaExcluida(int $listaId, $listas): bool
    {
        $lista = $listas->firstWhere('id', $listaId);
        return $lista && in_array($lista->nombre, ['COLABORADORES', 'PLATAFORMAS']);
    }

    private function crearNuevoCliente(array $data, $listas, array $mapaVendedoras): void
    {
        if (empty(trim($data['nombre'] ?? ''))) return;

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
        }

        // Default: Lista resuelta > Calculada por monto > Público General
        if (!$listaId) {
            $listaId = ($montoExtraido !== null) 
                ? $this->determinarListaPorMonto($montoExtraido, $listas) 
                : $listas->firstWhere('nombre', 'PUBLICO GENERAL')->id;
        }

        $clienteNuevo['lista_actual_id'] = $listaId;

        Cliente::create($clienteNuevo);
    }

    private function actualizarClienteExistente(Cliente $cliente, array $data, $listas, array $mapaVendedoras): ?array
    {
        $updateData = [];
        $listaOriginalId = $cliente->lista_actual_id; // Captura de estado previo
        $cambioReporte = null;
        
        if (!empty(trim($data['nombre'] ?? ''))) {
            $updateData['nombre'] = trim($data['nombre']);
        }

        if (!empty($data['vendedor_id'])) {
            $vendedorIdTraducido = $this->traducirVendedora($data['vendedor_id'], $mapaVendedoras);
            if ($vendedorIdTraducido) $updateData['vendedor_id'] = $vendedorIdTraducido;
        }

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
            
            $listaEvaluacion = $updateData['lista_actual_id'] ?? $cliente->lista_actual_id;

            if (!isset($updateData['lista_actual_id']) && !$this->esListaExcluida($listaEvaluacion, $listas)) {
                $nuevaListaIdPorMonto = $this->determinarListaPorMonto($montoExtraido, $listas);

                if ($cliente->lista_actual_id !== $nuevaListaIdPorMonto) {
                    $listaActual = $listas->firstWhere('id', $cliente->lista_actual_id);
                    $nuevaLista = $listas->firstWhere('id', $nuevaListaIdPorMonto);

                    if ($listaActual && $nuevaLista && ($nuevaLista->monto_requerido > $listaActual->monto_requerido)) {
                        $updateData['lista_actual_id'] = $nuevaListaIdPorMonto;
                    }
                }
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
            // Auditar si ocurrió un ascenso de categoría para el reporte
            if (isset($updateData['lista_actual_id']) && $updateData['lista_actual_id'] !== $listaOriginalId) {
                $listaAnterior = $listas->firstWhere('id', $listaOriginalId);
                $listaNueva = $listas->firstWhere('id', $updateData['lista_actual_id']);

                if ($listaAnterior && $listaNueva && ($listaNueva->monto_requerido > $listaAnterior->monto_requerido)) {
                    $cambioReporte = [
                        'numero_cliente' => $cliente->numero_cliente,
                        'nombre'         => $cliente->nombre,
                        'lista_anterior' => $listaAnterior->nombre,
                        'lista_nueva'    => $listaNueva->nombre,
                        'monto_nuevo'    => $updateData['monto_venta_actual'] ?? $cliente->monto_venta_actual
                    ];
                }
            }

            $cliente->update($updateData);
        }

        return $cambioReporte;
    }

    // --- UTILERÍAS ---
    private function resolverLista(array $data, $listas): ?int
    {
        if (empty(trim($data['codigo_lista'] ?? ''))) return null;
        
        $codigo = strtoupper(trim($data['codigo_lista']));

        if (isset($this->mapaWizerp[$codigo])) {
            return $listas->where('nombre', $this->mapaWizerp[$codigo])->first()->id ?? null;
        }
        
        return null;
    }

    private function limpiarMonto(?string $montoRaw): ?float
    {
        if ($montoRaw === null || trim($montoRaw) === '') return null;
        $montoLimpio = trim(str_replace(['$', ','], '', $montoRaw));
        if ($montoLimpio === '-') return 0.00;
        return is_numeric($montoLimpio) ? (float) $montoLimpio : null;
    }

    private function determinarListaPorMonto(float $monto, $listas): int
    {
        foreach ($listas as $lista) {
            if (in_array($lista->nombre, ['COLABORADORES', 'PLATAFORMAS'])) continue;
            if ($monto >= $lista->monto_requerido) return $lista->id;
        }
        return $listas->where('nombre', 'PUBLICO GENERAL')->first()->id; 
    }
}