<?php

namespace App\Support\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;

/** Lectura de la consulta nacional. No escribe la marca ni cambia el pedido. */
final class LecturaConsultaNacional
{
    /** @return array<string, mixed> */
    public static function de(PedidoBma $pedido): array
    {
        $pedido->loadMissing(['estatus', 'cajas', 'revisionesProducto']);

        return [
            'id' => $pedido->id,
            'folio' => $pedido->folio,
            'revision_registro' => (int) $pedido->revision_registro,
            'base_captura' => $pedido->base_captura,
            'solicitud_preparacion' => $pedido->solicitud_preparacion,
            'catalogo_paqueteria_id' => $pedido->catalogo_paqueteria_id,
            'estatus_envio' => $pedido->estatus_envio,
            'pesaje_solicitado_at' => $pedido->pesaje_solicitado_at?->toIso8601String(),
            'pesaje_respondido_at' => $pedido->pesaje_respondido_at?->toIso8601String(),
            'consulta_cerrada' => $pedido->consultaCerrada(),
            'consulta_cerrada_at' => $pedido->consulta_cerrada_at?->toIso8601String(),
            'consulta_actualizacion_pendiente' => (bool) $pedido->consulta_actualizacion_pendiente,
            'puede_cerrar_consulta' => $pedido->puedeCerrarConsulta(),
            'numero_remision' => $pedido->numero_remision,
            'costo_envio' => $pedido->costo_envio,
            'peso_real_kg' => $pedido->peso_real_kg,
            'numero_cajas' => $pedido->numero_cajas,
            'version_consulta' => (int) $pedido->historial()->max('id'),
            'cajas' => $pedido->cajas->map(fn (PedidoBmaCaja $caja) => [
                'id' => $caja->id,
                'uuid_operativo' => $caja->uuid_operativo,
                'peso_real_kg' => $caja->peso_real_kg,
                'peso_volumetrico_kg' => $caja->peso_volumetrico_kg,
                'peso_cobrado_kg' => $caja->peso_cobrado_kg,
                'estado_operativo' => $caja->estado_operativo,
            ])->values()->all(),
            'revisiones_producto' => $pedido->revisionesProducto->map(fn (PedidoBmaRevisionProducto $revision) => [
                'id' => $revision->id,
                'sku' => $revision->sku,
                'descripcion_producto' => $revision->descripcion_producto,
                'cantidad_solicitada' => $revision->cantidad_solicitada,
                'cantidad_encontrada' => $revision->cantidad_encontrada,
                'estado_fisico' => $revision->estado_fisico,
            ])->values()->all(),
        ];
    }
}
