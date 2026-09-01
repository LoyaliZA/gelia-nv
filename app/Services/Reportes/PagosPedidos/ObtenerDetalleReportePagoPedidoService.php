<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\User;
use App\Support\Reportes\AdminEstadoReportePagosPedidos;
use App\Support\Reportes\ClasificacionIngresoBancario;
use App\Support\Reportes\FechasPagoReporte;

class ObtenerDetalleReportePagoPedidoService
{
    public function ejecutar(User $usuario, PedidoBmaCierrePago $cierre, bool $incluirHistorico = false): array
    {
        $cierre->load([
            'pedido.estatus',
            'pedido.origen',
            'pedido.almacen',
            'cliente',
            'vendedor',
            'departamento',
            'almacen',
            'validadoPor',
            'items.reemplazaPago',
            'items.capturadoPor',
            'items.revisadoPor',
            'items.adminConfirmadoPor',
            'items.adminErrorReportadoPor',
            'adminPedidoErrorReportadoPor',
        ]);

        $remisionVigente = $cierre->pedido?->remision()->first();
        $remisionesHistoricas = $incluirHistorico && $usuario->can('reportes.pagos_pedidos.ver_historico')
            ? $cierre->pedido?->remisionesHistoricas()->get() ?? collect()
            : collect();

        return [
            'cierre' => [
                'id' => $cierre->id,
                'version' => $cierre->version,
                'estado' => $cierre->estado,
                'origen' => $cierre->origen,
                'validado_at' => $cierre->validado_at?->toIso8601String(),
                'validado_por' => $cierre->validadoPor?->only(['id', 'name']),
                'folio' => $cierre->folio_snapshot,
                'folio_remision' => $cierre->folio_remision_snapshot,
                'pedido_fecha' => $cierre->pedido_fecha?->toDateString(),
                'cliente' => $cierre->cliente ? [
                    'id' => $cierre->cliente->id,
                    'nombre' => $cierre->cliente->nombre,
                    'numero_cliente' => $cierre->cliente->numero_cliente,
                ] : null,
                'vendedor' => $cierre->vendedor?->only(['id', 'name']),
                'departamento' => $cierre->departamento?->only(['id', 'nombre']),
                'almacen' => $cierre->almacen?->only(['id', 'nombre']),
                'origen_pedido' => $cierre->metadata_snapshot['origen'] ?? $cierre->pedido?->origen?->nombre,
                'estatus_pedido' => $cierre->metadata_snapshot['estatus'] ?? $cierre->pedido?->estatus?->nombre_visual,
                ...AdminEstadoReportePagosPedidos::payloadCierre($cierre),
            ],
            'financiero' => [
                'monto_venta' => $cierre->monto_venta,
                'monto_envio' => $cierre->monto_envio,
                'monto_seguro' => $cierre->monto_seguro,
                'total_pedido' => $cierre->total_pedido,
                'saf_aplicado' => $cierre->saf_aplicado,
                'total_a_cobrar' => $cierre->total_a_cobrar,
                'pagos_validos' => $cierre->pagos_validos,
                'diferencia' => $cierre->diferencia,
                'excedente' => $cierre->excedente,
                'tolerancia_aplicada' => $cierre->tolerancia_aplicada,
                'estado_cobertura' => $cierre->estado_cobertura,
            ],
            'exhibiciones' => $cierre->items->map(function ($item) use ($usuario, $cierre) {
                $puedeEvidencia = $usuario->can('reportes.pagos_pedidos.ver_evidencias');
                $fechas = FechasPagoReporte::exhibicion($item, $cierre);

                $clasificacion = ClasificacionIngresoBancario::clasificarItem($item);

                return [
                    'id' => $item->id,
                    'pago_id' => $item->pedido_bma_pago_id,
                    'numero_exhibicion' => $item->numero_exhibicion,
                    'monto' => $item->monto_snapshot,
                    'forma_pago' => $item->forma_pago_snapshot,
                    'forma_pago_label' => \App\Models\SaldosAFavor\PedidoBmaPago::labelForma($item->forma_pago_snapshot),
                    'banco' => $item->banco_snapshot,
                    'referencia' => $item->referencia_snapshot,
                    'fecha_pago' => $fechas['pago'],
                    'fecha_pago_label' => $fechas['pago_label'],
                    'capturado_por' => $item->capturadoPor?->name,
                    'capturado_at' => $fechas['reportada'],
                    'capturado_at_label' => $fechas['reportada_label'],
                    'validado_at' => $fechas['validacion'],
                    'validado_at_label' => $fechas['validacion_label'],
                    'pedido_fecha' => $fechas['pedido'],
                    'pedido_fecha_label' => $fechas['pedido_label'],
                    'reportado_posteriormente' => $fechas['reportado_posteriormente'],
                    'clasificacion_ingreso' => $clasificacion,
                    'clasificacion_ingreso_label' => ClasificacionIngresoBancario::labelClasificacion($clasificacion),
                    'cuenta_ingreso_bancario' => ClasificacionIngresoBancario::cuentaIngresoBancario($item),
                    'estado_revision' => $item->estado_revision_snapshot,
                    'activo_para_cobertura' => $item->activo_para_cobertura_snapshot,
                    'motivo_rechazo' => $item->motivo_rechazo_snapshot,
                    'reemplaza_pago_id' => $item->reemplaza_pago_id,
                    'revisado_por' => $item->revisadoPor?->name,
                    'revisado_at' => $item->revisado_at_snapshot?->toIso8601String(),
                    'evidencia' => ($puedeEvidencia && $item->ruta_archivo_snapshot) ? [
                        'nombre' => $item->nombre_archivo_snapshot,
                        'mime_type' => $item->mime_type_snapshot,
                        'tamano_bytes' => $item->tamano_bytes_snapshot,
                        'url' => route('reportes.pagos_pedidos.evidencia_pago', ['pago' => $item->pedido_bma_pago_id]),
                    ] : null,
                    ...AdminEstadoReportePagosPedidos::payloadItem($item),
                ];
            })->values()->all(),
            'documentos' => [
                'remision_vigente' => ($usuario->can('reportes.pagos_pedidos.ver_evidencias') && $remisionVigente) ? [
                    'id' => $remisionVigente->id,
                    'nombre' => $remisionVigente->nombre_original,
                    'folio' => $cierre->folio_remision_snapshot,
                    'mime_type' => $remisionVigente->mime_type,
                    'tamano_bytes' => $remisionVigente->tamano_bytes,
                    'created_at' => $remisionVigente->created_at?->toIso8601String(),
                    'url' => route('reportes.pagos_pedidos.documento', ['documento' => $remisionVigente->id]),
                ] : ($remisionVigente ? [
                    'id' => $remisionVigente->id,
                    'nombre' => $remisionVigente->nombre_original,
                    'folio' => $cierre->folio_remision_snapshot,
                    'mime_type' => $remisionVigente->mime_type,
                    'tamano_bytes' => $remisionVigente->tamano_bytes,
                    'created_at' => $remisionVigente->created_at?->toIso8601String(),
                    'url' => null,
                ] : null),
                'remisiones_historicas' => $remisionesHistoricas->map(fn ($d) => [
                    'id' => $d->id,
                    'nombre' => $d->nombre_original,
                    'mime_type' => $d->mime_type,
                    'sustituido_at' => $d->sustituido_at?->toIso8601String(),
                    'url' => $usuario->can('reportes.pagos_pedidos.ver_evidencias')
                        ? route('reportes.pagos_pedidos.documento', ['documento' => $d->id])
                        : null,
                ])->values()->all(),
            ],
        ];
    }
}
