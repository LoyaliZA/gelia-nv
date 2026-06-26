<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\CatalogoEstatusPago;
use App\Models\Contabilidad\CatalogoTipoTransaccion;
use App\Models\Contabilidad\Pedido;
use App\Models\Contabilidad\PedidoLinea;
use App\Models\Contabilidad\PlataformaPago;
use Illuminate\Support\Facades\DB;

class CrearPedidoContabilidadService
{
    public function __construct(
        private CalcularComisionPlataformaService $comisionService,
    ) {}

    public function ejecutar(array $datos): Pedido
    {
        return DB::transaction(function () use ($datos) {
            $plataforma = PlataformaPago::query()->findOrFail($datos['plataforma_pago_id']);
            $ventaTotal = (float) $datos['venta_total'];
            $costoEnvio = (float) ($datos['costo_envio'] ?? 0);
            $envioPagadoCliente = (bool) ($datos['envio_pagado_cliente'] ?? false);

            $tipoId = CatalogoTipoTransaccion::resolverIdPorCodigo($datos['tipo_transaccion'] ?? 'venta');
            $tipo = CatalogoTipoTransaccion::query()->findOrFail($tipoId);
            $txCode = strtolower($tipo->codigo);

            $costoProductos = 0.0;
            foreach ($datos['productos'] as $prod) {
                $tipoDevolucion = $prod['tipo_devolucion'] ?? 'normal';
                $esCosto = ($txCode === 'reembolso')
                    ? ($tipoDevolucion === 'perdido_danado')
                    : ($tipoDevolucion === 'normal' || $tipoDevolucion === 'perdido_danado');

                if ($esCosto) {
                    $costoProductos += (float) $prod['precio'] * (int) $prod['piezas'];
                }
            }

            $desglose = $this->comisionService->ejecutar($ventaTotal, $plataforma);
            $comisionFinal = isset($datos['comision_real']) && $datos['comision_real'] !== ''
                ? (float) $datos['comision_real']
                : $desglose['comision_total'];

            if (abs($comisionFinal - $desglose['comision_total']) > 0.01) {
                $factor = 1 + ((float) $plataforma->tasa_iva_pct / 100);
                $desglose['comision_base'] = round($comisionFinal / $factor, 2);
                $desglose['comision_iva'] = round($comisionFinal - $desglose['comision_base'], 2);
            }

            $pedido = new Pedido([
                'fecha_salida' => $datos['fecha_salida'],
                'numero_pedido' => $datos['numero_pedido'],
                'cliente_nombre' => $datos['cliente_nombre'] ?? null,
                'tipo_transaccion_id' => $tipoId,
                'plataforma_pago_id' => $plataforma->id,
                'venta_total' => $ventaTotal,
                'costo_envio' => $costoEnvio,
                'envio_pagado_cliente' => $envioPagadoCliente,
                'comision_base' => $desglose['comision_base'],
                'comision_iva' => $desglose['comision_iva'],
                'tasa_comision_pct' => $plataforma->tasa_comision_pct,
                'cuota_fija' => $plataforma->cuota_fija,
                'comision_plataforma' => $comisionFinal,
                'estatus_pago_id' => CatalogoEstatusPago::PENDIENTE,
            ]);
            $pedido->setRelation('tipoTransaccion', $tipo);
            $pedido->utilidad_total = $pedido->calcularUtilidad($costoProductos);
            $pedido->save();

            foreach ($datos['productos'] as $prod) {
                $piezas = (int) $prod['piezas'];
                $precio = (float) $prod['precio'];
                $tipoDevolucion = $prod['tipo_devolucion'] ?? 'normal';

                PedidoLinea::query()->create([
                    'pedido_id' => $pedido->id,
                    'sku' => $prod['sku'],
                    'piezas' => $piezas,
                    'nombre_producto' => $prod['nombre'],
                    'precio_unitario' => $precio,
                    'subtotal' => round($precio * $piezas, 2),
                    'tipo_devolucion' => $tipoDevolucion,
                ]);
            }

            return $pedido->load(['plataformaPago', 'estatusPago', 'tipoTransaccion', 'lineas']);
        });
    }
}
