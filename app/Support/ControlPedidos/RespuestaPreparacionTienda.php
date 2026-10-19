<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;

final class RespuestaPreparacionTienda
{
    public static function datos(PedidoBmaTareaPreparacion $tarea): array
    {
        $tarea->loadMissing(['productos', 'atendidaPor', 'cumplimientoFisico', 'documentos']);
        $tipos = [PedidoBmaTareaDocumento::TIPO_EVIDENCIA_PRODUCTO, PedidoBmaTareaDocumento::TIPO_EVIDENCIA_GENERAL, PedidoBmaTareaDocumento::TIPO_EVIDENCIA_INCIDENCIA, PedidoBmaTareaDocumento::TIPO_CARATULA_RECOGE];

        return [
            'estado' => $tarea->estado,
            'respondida' => $tarea->atendida_at !== null,
            'respondida_at' => $tarea->atendida_at?->toIso8601String(),
            'responsable' => $tarea->atendidaPor?->name,
            'ubicacion' => $tarea->cumplimientoFisico?->ubicacion,
            'cantidad' => (int) $tarea->productos->sum('cantidad_encontrada'),
            'observaciones' => $tarea->observaciones_respuesta,
            'productos' => $tarea->productos->map(fn ($p) => [
                'id' => $p->id, 'sku' => $p->sku, 'descripcion' => $p->descripcion_snapshot,
                'cantidad' => $p->cantidad_encontrada, 'estado_fisico' => $p->estado_fisico, 'observacion' => $p->observacion,
                'unica_pieza' => (bool) $p->unica_pieza, 'mejor_ejemplar' => (bool) $p->mejor_ejemplar,
            ])->values()->all(),
            'documentos' => $tarea->documentos->whereIn('tipo_evidencia', $tipos)->map(fn ($d) => [
                'id' => $d->id, 'producto_id' => $d->pedido_bma_tarea_producto_id, 'tipo' => $d->tipo_evidencia,
                'nombre_original' => $d->nombre_original, 'mime_type' => $d->mime_type,
                'url' => route('control_pedidos.preparacion.documento', [$tarea->pedido_bma_id, $tarea->id, $d->id]),
            ])->values()->all(),
        ];
    }
}
