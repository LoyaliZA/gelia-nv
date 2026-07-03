<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\CatalogoTipoTransaccion;
use App\Models\Contabilidad\Pedido;
use Illuminate\Support\Facades\DB;

class ActualizarPedidoContabilidadService
{
    public function ejecutar(Pedido $pedido, array $datos): Pedido
    {
        return DB::transaction(function () use ($pedido, $datos) {
            if ($pedido->bloqueado) {
                throw new \RuntimeException('El periodo está bloqueado y no puede ser editado.');
            }

            $pedido->loadMissing(['lineas', 'tipoTransaccion', 'estatusPago']);

            $tipoId = CatalogoTipoTransaccion::resolverIdPorCodigo($datos['tipo_transaccion']);
            $tipo = CatalogoTipoTransaccion::query()->findOrFail($tipoId);
            $txCode = strtolower($tipo->codigo);

            $costoProductos = 0.0;
            foreach ($datos['productos'] as $prodReq) {
                $linea = $pedido->lineas->firstWhere('id', $prodReq['id']);
                if (! $linea) {
                    continue;
                }
                $piezas = (int) $prodReq['piezas'];
                $tipoDevolucion = $prodReq['tipo_devolucion'] ?? 'normal';
                $subtotal = round((float) $linea->precio_unitario * $piezas, 2);
                $linea->update([
                    'piezas' => $piezas,
                    'subtotal' => $subtotal,
                    'tipo_devolucion' => $tipoDevolucion,
                ]);
                
                $esCosto = ($txCode === 'reembolso')
                    ? ($tipoDevolucion === 'perdido_danado')
                    : ($tipoDevolucion === 'normal' || $tipoDevolucion === 'perdido_danado');

                if ($esCosto) {
                    $costoProductos += $subtotal;
                }
            }

            $envioPagadoCliente = (bool) ($datos['envio_pagado_cliente'] ?? false);

            $pedido->fill([
                'fecha_salida' => $datos['fecha_salida'] ?? $pedido->fecha_salida,
                'tipo_transaccion_id' => $tipoId,
                'plataforma_pago_id' => $datos['plataforma_pago_id'],
                'cliente_nombre' => $datos['cliente_nombre'] ?? null,
                'venta_total' => $datos['venta_total'],
                'costo_envio' => $datos['costo_envio'],
                'envio_pagado_cliente' => $envioPagadoCliente,
                'comision_plataforma' => $datos['comision_plataforma'],
            ]);
            $pedido->setRelation('tipoTransaccion', $tipo);
            $pedido->utilidad_total = $pedido->calcularUtilidad($costoProductos);
            $pedido->save();

            return $pedido->fresh(['plataformaPago', 'estatusPago', 'tipoTransaccion', 'lineas']);
        });
    }
}
