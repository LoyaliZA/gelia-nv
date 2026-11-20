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
        $pedido->loadMissing(['estatus', 'cajas', 'documentos', 'revisionesProducto', 'complementos.revisionesProducto', 'complementos.documentos', 'sucursalDestino']);
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $vinculo = is_array($json['vinculo_resguardo'] ?? null) ? $json['vinculo_resguardo'] : null;
        $vinculoPedidoId = $pedido->id;
        if ($vinculo === null) {
            foreach ($pedido->complementos as $hijo) {
                $marca = $hijo->solicitud_preparacion_json['vinculo_resguardo'] ?? null;
                if (is_array($marca) && isset($marca['estado'])) {
                    $vinculo = $marca;
                    $vinculoPedidoId = (int) $hijo->id;
                    break;
                }
            }
        }

        return [
            'id' => $pedido->id,
            'folio' => $pedido->folio,
            'revision_registro' => (int) $pedido->revision_registro,
            'base_captura' => $pedido->base_captura,
            'solicitud_preparacion' => $pedido->solicitud_preparacion,
            'catalogo_paqueteria_id' => $pedido->catalogo_paqueteria_id,
            'estatus' => $pedido->estatus,
            'origen_id' => $pedido->origen_id,
            'soporte_pedido' => $pedido->documentos->first(fn ($doc) => $doc->activo
                && in_array($doc->tipo, ['pdf_pedido', 'cotizacion'], true)),
            'almacen_id' => $pedido->almacen_id,
            'mercancia_pagada' => isset($json['pago_solo_mercancia_nacional']),
            'aplica_seguro' => (bool) $pedido->aplica_seguro,
            'cliente_direccion_id' => $pedido->cliente_direccion_id,
            'catalogo_tipo_guia_id' => $pedido->catalogo_tipo_guia_id,
            'catalogo_zona_id' => $pedido->catalogo_zona_id,
            'costo_seguro' => $pedido->costo_seguro,
            'total_mercancia' => $pedido->total_mercancia,
            'saldo_a_favor' => $pedido->saldo_a_favor,
            'pago_validado_at' => $pedido->pago_validado_at?->toIso8601String(),
            'documentos' => $pedido->documentos->where('activo', true)->values()->all(),
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
            'peso_cobrado_guia_kg' => $pedido->peso_cobrado_guia_kg,
            'numero_cajas' => $pedido->numero_cajas,
            'sucursal_destino_id' => $pedido->sucursal_destino_id,
            'sucursal_destino' => $pedido->sucursalDestino ? [
                'id' => $pedido->sucursalDestino->id,
                'nombre' => $pedido->sucursalDestino->nombre,
            ] : null,
            'cambio_a_tienda' => is_array($json['cambio_a_tienda'] ?? null) ? $json['cambio_a_tienda'] : null,
            'vinculo_resguardo' => $vinculo,
            'vinculo_pedido_id' => $vinculoPedidoId,
            'invalidacion_guia' => is_array($json['invalidacion_guia'] ?? null) ? $json['invalidacion_guia'] : null,
            'version_consulta' => (int) $pedido->historial()->max('id'),
            'cajas' => $pedido->cajas->map(fn (PedidoBmaCaja $caja) => [
                'id' => $caja->id,
                'uuid_operativo' => $caja->uuid_operativo,
                'peso_real_kg' => $caja->peso_real_kg,
                'peso_volumetrico_kg' => $caja->peso_volumetrico_kg,
                'peso_cobrado_kg' => $caja->peso_cobrado_kg,
                'costo_envio' => $caja->costo_envio,
                'estado_operativo' => $caja->estado_operativo,
                'estatus_recoleccion' => $caja->estatus_recoleccion,
            ])->values()->all(),
            'revisiones_producto' => $pedido->revisionesProducto->whereNull('retirada_at')->values()->map(fn (PedidoBmaRevisionProducto $revision) => [
                'id' => $revision->id,
                'sku' => $revision->sku,
                'descripcion_producto' => $revision->descripcion_producto,
                'cantidad_solicitada' => $revision->cantidad_solicitada,
                'cantidad_encontrada' => $revision->cantidad_encontrada,
                'estado_fisico' => $revision->estado_fisico,
                'comentario' => $revision->comentario,
                'resolucion' => $revision->resolucion,
            ])->values()->all(),
            'complementos' => $pedido->complementos->map(fn (PedidoBma $hijo) => [
                'id' => $hijo->id,
                'folio' => $hijo->folio,
                'folio_remision' => $hijo->folio_remision,
                'solicitud_preparacion' => $hijo->solicitud_preparacion,
                'revisiones_producto' => $hijo->revisionesProducto->map(fn (PedidoBmaRevisionProducto $revision) => [
                    'id' => $revision->id,
                    'sku' => $revision->sku,
                    'descripcion_producto' => $revision->descripcion_producto,
                    'cantidad_solicitada' => $revision->cantidad_solicitada,
                    'cantidad_encontrada' => $revision->cantidad_encontrada,
                    'estado_fisico' => $revision->estado_fisico,
                ])->values()->all(),
                'documentos' => $hijo->documentos->map(fn ($documento) => [
                    'id' => $documento->id,
                    'tipo' => $documento->tipo,
                    'nombre_original' => $documento->nombre_original,
                    'url' => $documento->url ?? null,
                    'relacion_tipo' => $documento->relacion_tipo,
                    'relacion_id' => $documento->relacion_id,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
