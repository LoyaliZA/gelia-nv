<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;

final class SerializadorPedidoRevisionResguardoPdv
{
    /**
     * Snapshot de revisión física y evidencias del pedido para vistas PDV (detalle y entrega).
     *
     * @return array<string, mixed>|null
     */
    public static function desdePedido(?PedidoBma $pedido): ?array
    {
        if (! $pedido instanceof PedidoBma) {
            return null;
        }

        $pedido->loadMissing([
            'revisionesProducto',
            'documentos' => fn ($q) => $q->vigente()->orderBy('orden')->orderBy('id'),
            'cajas' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
        ]);

        $revisiones = $pedido->revisionesProducto
            ->sortBy('orden')
            ->values()
            ->map(fn (PedidoBmaRevisionProducto $revision) => [
                'id' => $revision->id,
                'orden' => (int) $revision->orden,
                'descripcion_producto' => $revision->descripcion_producto,
                'producto_id' => $revision->producto_id,
                'sku' => $revision->sku,
                'estado_fisico' => $revision->estado_fisico,
                'comentario' => $revision->comentario,
                'unica_pieza' => (bool) $revision->unica_pieza,
                'mejor_ejemplar' => (bool) $revision->mejor_ejemplar,
                'resolucion' => $revision->resolucion,
                'resolucion_etiqueta' => $revision->resolucion_etiqueta,
                'resolucion_nota' => $revision->resolucion_nota,
            ])
            ->all();

        $documentos = $pedido->documentos
            ->filter(fn (PedidoBmaDocumento $documento) => in_array($documento->tipo, [
                PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                PedidoBmaDocumento::TIPO_EVIDENCIA_APARTADO,
                PedidoBmaDocumento::TIPO_EVIDENCIA_PESAJE,
            ], true))
            ->values()
            ->map(fn (PedidoBmaDocumento $documento) => [
                'id' => $documento->id,
                'tipo' => $documento->tipo,
                'url' => $documento->url,
                'nombre_original' => $documento->nombre_original,
                'mime_type' => $documento->mime_type,
                'relacion_tipo' => $documento->relacion_tipo,
                'relacion_id' => $documento->relacion_id,
                'comentario' => $documento->comentario,
            ])
            ->all();

        $cajas = $pedido->cajas
            ->map(fn ($caja) => [
                'id' => $caja->id,
                'orden' => (int) ($caja->orden ?? 0),
            ])
            ->values()
            ->all();

        if (
            $revisiones === []
            && $documentos === []
            && ! $pedido->estado_fisico_general
            && ! $pedido->tiene_observaciones_fisicas
        ) {
            return null;
        }

        return [
            'id' => $pedido->id,
            'folio' => $pedido->folio,
            'folio_remision' => $pedido->folio_remision,
            'estado_fisico_general' => $pedido->estado_fisico_general,
            'comentario_fisico_general' => $pedido->comentario_fisico_general,
            'tiene_observaciones_fisicas' => (bool) $pedido->tiene_observaciones_fisicas,
            'revisiones_producto' => $revisiones,
            'documentos' => $documentos,
            'cajas' => $cajas,
        ];
    }
}
