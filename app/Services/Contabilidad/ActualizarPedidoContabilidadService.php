<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\CatalogoTipoTransaccion;
use App\Models\Contabilidad\Pedido;
use Illuminate\Support\Facades\DB;

class ActualizarPedidoContabilidadService
{
    public function __construct(
        private CalcularUtilidadPedidoService $utilidadService,
    ) {}

    public function ejecutar(Pedido $pedido, array $datos): Pedido
    {
        return DB::transaction(function () use ($pedido, $datos) {
            if ($pedido->bloqueado) {
                throw new \RuntimeException('El periodo está bloqueado y no puede ser editado.');
            }

            $pedido->loadMissing(['lineas', 'tipoTransaccion', 'estatusPago']);

            $tipoId = $this->resolverTipoTransaccionId($datos['tipo_transaccion']);
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
                
                $esCosto = false;
                if ($txCode === 'reembolso') {
                    if ($tipoDevolucion === 'perdido_danado') {
                        $esCosto = true;
                    }
                } else {
                    if ($tipoDevolucion === 'normal' || $tipoDevolucion === 'perdido_danado') {
                        $esCosto = true;
                    }
                }

                if ($esCosto) {
                    $costoProductos += $subtotal;
                }
            }

            $envioPagadoCliente = (bool) ($datos['envio_pagado_cliente'] ?? false);

            $utilidad = $this->utilidadService->ejecutar(
                (float) $datos['venta_total'],
                $costoProductos,
                (float) $datos['costo_envio'],
                $envioPagadoCliente,
                (float) $datos['comision_plataforma'],
                $tipo->codigo,
                $pedido->estatusPago?->codigo ?? 'pendiente',
                (float) $pedido->comision_transferencia,
            );

            $pedido->update([
                'tipo_transaccion_id' => $tipoId,
                'plataforma_pago_id' => $datos['plataforma_pago_id'],
                'cliente_nombre' => $datos['cliente_nombre'] ?? null,
                'venta_total' => $datos['venta_total'],
                'costo_envio' => $datos['costo_envio'],
                'envio_pagado_cliente' => $envioPagadoCliente,
                'comision_plataforma' => $datos['comision_plataforma'],
                'utilidad_total' => $utilidad,
            ]);

            return $pedido->fresh(['plataformaPago', 'estatusPago', 'tipoTransaccion', 'lineas']);
        });
    }

    private function resolverTipoTransaccionId(string $tipo): int
    {
        $codigo = strtolower($tipo);

        return match (true) {
            str_contains($codigo, 'contracargo') => CatalogoTipoTransaccion::CONTRACARGO,
            str_contains($codigo, 'reembolso') => CatalogoTipoTransaccion::REEMBOLSO,
            default => CatalogoTipoTransaccion::VENTA,
        };
    }
}
