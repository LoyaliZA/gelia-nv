<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;

final class RespuestaPreparacionTienda
{
    public static function datos(PedidoBmaTareaPreparacion $tarea): array
    {
        $tarea->loadMissing(['productos', 'atendidaPor', 'cumplimientoFisico', 'documentos', 'pedido.revisionesProducto', 'pedido.documentos']);
        $tipos = [PedidoBmaTareaDocumento::TIPO_EVIDENCIA_PRODUCTO, PedidoBmaTareaDocumento::TIPO_EVIDENCIA_GENERAL, PedidoBmaTareaDocumento::TIPO_EVIDENCIA_INCIDENCIA, PedidoBmaTareaDocumento::TIPO_CARATULA_RECOGE];
        $pedido = $tarea->pedido;
        $revision = is_array($pedido?->solicitud_preparacion_json['revision_disponibilidad'] ?? null)
            ? $pedido->solicitud_preparacion_json['revision_disponibilidad']
            : [];
        $excepciones = $pedido?->revisionesProducto
            ?->whereIn('estado_fisico', ['regular', 'malo', 'danado', 'sin_existencia'])
            ->values() ?? collect();
        $estadosExcepcion = ['regular', 'malo', 'danado', 'sin_existencia'];
        $soloAfectadas = $tarea->productos->isNotEmpty()
            && $tarea->productos->every(fn ($producto) => in_array($producto->estado_fisico, $estadosExcepcion, true));
        $piezasAfectadas = $soloAfectadas || ($tarea->productos->isEmpty() && $excepciones->isNotEmpty());
        $cantidad = $piezasAfectadas
            ? (int) ($excepciones->isNotEmpty() ? $excepciones->sum('cantidad_solicitada') : $tarea->productos->sum(fn ($producto) => max(1, (int) ($producto->cantidad_solicitada ?: $producto->cantidad_encontrada))))
            : (int) $tarea->productos->sum('cantidad_encontrada');
        $documentos = $tarea->documentos->whereIn('tipo_evidencia', $tipos)->map(fn ($d) => [
            'id' => $d->id, 'producto_id' => $d->pedido_bma_tarea_producto_id, 'tipo' => $d->tipo_evidencia,
            'nombre_original' => $d->nombre_original, 'mime_type' => $d->mime_type,
            'url' => route('control_pedidos.preparacion.documento', [$tarea->pedido_bma_id, $tarea->id, $d->id]),
        ])->values();
        $rutas = $tarea->documentos->pluck('ruta_interna')->filter()->all();
        $nombresGenerales = $tarea->documentos
            ->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_EVIDENCIA_GENERAL)
            ->pluck('nombre_original')
            ->all();
        foreach ($pedido?->documentos ?? [] as $documento) {
            if (! $documento->activo || $documento->relacion_tipo !== PedidoBmaDocumento::RELACION_REVISION_GENERAL || (int) $documento->relacion_id !== (int) $tarea->id) {
                continue;
            }
            if (in_array($documento->ruta_archivo, $rutas, true) || in_array($documento->nombre_original, $nombresGenerales, true)) {
                continue;
            }
            $documentos->push([
                'id' => $documento->id,
                'producto_id' => null,
                'tipo' => 'evidencia_general',
                'nombre_original' => $documento->nombre_original,
                'mime_type' => $documento->mime_type,
                'url' => $documento->url,
            ]);
        }
        if (isset($revision['evidencia_actual_ids'])) {
            $documentos = $documentos->filter(fn ($d) => $d['producto_id'] !== null)->values();
            foreach ($pedido->documentos as $documento) {
                if (! $documento->activo || $documento->relacion_tipo !== PedidoBmaDocumento::RELACION_REVISION_GENERAL) {
                    continue;
                }
                $documentos->push(['id' => $documento->id, 'producto_id' => null, 'tipo' => 'evidencia_general',
                    'nombre_original' => $documento->nombre_original, 'mime_type' => $documento->mime_type, 'url' => $documento->url,
                    'anterior' => ! in_array($documento->id, $revision['evidencia_actual_ids'], true)]);
            }
        }
        $responsableId = $revision['respondida_por_id'] ?? null;
        $responsableRevision = $responsableId ? User::query()->whereKey($responsableId)->value('name') : null;
        $apartado = $tarea->cumplimientoFisico?->estado === PedidoBmaCumplimientoFisico::ESTADO_APARTADO_CONFIRMADO;

        return [
            'documental' => ApartadoT01::aplica($pedido),
            'estado' => $tarea->estado,
            'respondida' => $tarea->atendida_at !== null,
            'respondida_at' => $revision['respondida_at'] ?? $tarea->atendida_at?->toIso8601String(),
            'responsable' => $responsableRevision ?: $tarea->atendidaPor?->name,
            'ubicacion' => $tarea->cumplimientoFisico?->ubicacion,
            'cantidad' => $cantidad,
            'piezas_afectadas' => $piezasAfectadas,
            'apartado_confirmado' => $apartado,
            'pago_presencial' => $apartado ? ($tarea->cumplimientoFisico?->pago_confirmado_at ? 'confirmado' : 'pendiente') : null,
            'entrega' => $apartado ? ($tarea->cumplimientoFisico?->entregada_at ? 'registrada' : 'pendiente') : null,
            'observaciones' => $tarea->observaciones_respuesta,
            'revision' => $revision === [] ? null : [
                'estado' => $revision['estado'] ?? null,
                'respondida_at' => $revision['respondida_at'] ?? null,
                'responsable' => $responsableRevision,
                'aceptacion_vigente' => ($revision['aceptacion_vigente'] ?? true) !== false && ($revision['vigente'] ?? true) !== false && empty($revision['sustituida_at']),
            ],
            'excepciones' => $excepciones->map(fn ($revisionProducto) => [
                'id' => $revisionProducto->id,
                'sku' => $revisionProducto->sku,
                'descripcion' => $revisionProducto->descripcion_producto,
                'cantidad' => $revisionProducto->cantidad_solicitada,
                'estado_fisico' => $revisionProducto->estado_fisico,
                'detalle' => $revisionProducto->comentario,
                'unica_pieza' => (bool) $revisionProducto->unica_pieza,
                'mejor_ejemplar' => (bool) $revisionProducto->mejor_ejemplar,
                'documentos' => $pedido->documentos->filter(fn ($d) => $d->activo && $d->relacion_tipo === PedidoBmaDocumento::RELACION_REVISION_PRODUCTO && (int) $d->relacion_id === (int) $revisionProducto->id)->map(fn ($d) => ['id' => $d->id, 'url' => $d->url, 'mime_type' => $d->mime_type, 'nombre_original' => $d->nombre_original])->values()->all(),
            ])->values()->all(),
            'productos' => (count($revision['historial_respuestas'] ?? []) > 0 ? collect() : $tarea->productos)->map(fn ($p) => [
                'id' => $p->id, 'sku' => $p->sku, 'descripcion' => $p->descripcion_snapshot,
                'cantidad' => $p->cantidad_encontrada, 'estado_fisico' => $p->estado_fisico, 'observacion' => $p->observacion,
                'unica_pieza' => $p->estado_fisico !== 'sin_existencia' && (bool) $p->unica_pieza, 'mejor_ejemplar' => $p->estado_fisico !== 'sin_existencia' && (bool) $p->mejor_ejemplar,
            ])->values()->all(),
            'documentos' => $documentos->values()->all(),
        ];
    }
}
