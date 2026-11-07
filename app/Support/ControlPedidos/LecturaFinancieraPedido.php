<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\SafPedidoAplicacion;

/** Lectura de importes, SAF, solicitud y revisión. No muta el pedido. */
final class LecturaFinancieraPedido
{
    /** @return array<string, mixed> */
    public static function de(PedidoBma $pedido): array
    {
        $pedido->refresh();

        return [
            'estatus' => $pedido->estatus,
            'catalogo_estatus_pedido_id' => $pedido->catalogo_estatus_pedido_id,
            'sucursal_destino_id' => $pedido->sucursal_destino_id,
            'sucursal_destino' => $pedido->sucursalDestino,
            'pago_validado_at' => $pedido->pago_validado_at?->toIso8601String(),
            'numero_remision' => $pedido->numero_remision,
            'empacado_at' => $pedido->empacado_at?->toIso8601String(),
            'consulta_cerrada' => $pedido->consultaCerrada(),
            'consulta_cerrada_at' => $pedido->consulta_cerrada_at?->toIso8601String(),
            'revisiones_producto' => $pedido->revisionesProducto()->orderBy('orden')->get(),
            'puede_cerrar_consulta' => $pedido->puedeCerrarConsulta(),
            'motivo_bloqueo_cierre' => $pedido->motivoBloqueoCierreConsultaTienda(),
            'tarea_preparacion' => ($tarea = $pedido->instruccionEntregaVigente)
                ? [
                    'id' => $tarea->id, 'estado' => $tarea->estado, 'version' => $tarea->version,
                    'almacen' => $tarea->almacen, 'modalidad' => $tarea->modalidad,
                    'respuesta' => RespuestaPreparacionTienda::datos($tarea),
                ] : null,
            'documentos' => $pedido->documentos()->where('activo', true)->get(),
            'total_mercancia' => $pedido->total_mercancia,
            'costo_envio' => $pedido->costo_envio,
            'aplica_seguro' => (bool) $pedido->aplica_seguro,
            'costo_seguro' => $pedido->costo_seguro,
            'saldo_a_favor' => $pedido->saldo_a_favor,
            'total_a_cobrar' => $pedido->total_a_cobrar,
            'saf_aplicaciones' => $pedido->safAplicaciones()
                ->where('estado', '!=', SafPedidoAplicacion::ESTADO_LIBERADO)
                ->get(['saf_credito_id', 'monto', 'estado'])
                ->map(fn (SafPedidoAplicacion $aplicacion) => [
                    'saf_credito_id' => (int) $aplicacion->saf_credito_id,
                    'monto' => (float) $aplicacion->monto,
                    'estado' => $aplicacion->estado,
                ])->values()->all(),
            'revision_registro' => (int) $pedido->revision_registro,
            'base_captura' => $pedido->base_captura,
            'solicitud_preparacion' => $pedido->solicitud_preparacion,
            'atencion_md' => AtencionMismoDiaTienda::datos($pedido),
            'prioridad_md' => (bool) $pedido->prioridad_md,
        ];
    }
}
