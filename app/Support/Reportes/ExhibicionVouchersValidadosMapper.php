<?php

namespace App\Support\Reportes;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Support\Reportes\AdminEstadoReportePagosPedidos;

final class ExhibicionVouchersValidadosMapper
{
    /**
     * @param  array<int, true>  $posiblesDuplicados
     * @return array<string, mixed>
     */
    public function fila(
        PedidoBmaCierrePagoItem $item,
        PedidoBmaCierrePago $cierre,
        User $usuario,
        array $posiblesDuplicados = [],
    ): array {
        $fechas = FechasPagoReporte::exhibicion($item, $cierre);
        $clasificacion = ClasificacionIngresoBancario::clasificarItem($item);
        $estadoRevision = (string) ($item->estado_revision_snapshot ?? '');
        $puedeEvidencia = $usuario->can('reportes.pagos_pedidos.ver_evidencias');
        $tieneVoucher = AlcanceExhibicionesReportePagosPedidos::itemTieneVoucher($item);
        $saf = (float) ($cierre->saf_aplicado ?? 0);

        return [
            'id' => $item->id,
            'pago_id' => $item->pedido_bma_pago_id,
            'numero_exhibicion' => $item->numero_exhibicion,
            'folio_pedido' => $cierre->folio_snapshot,
            'folio_remision' => $cierre->folio_remision_snapshot,
            'cliente' => $cierre->cliente ? [
                'nombre' => $cierre->cliente->nombre,
                'numero_cliente' => $cierre->cliente->numero_cliente,
            ] : null,
            'monto' => $item->monto_snapshot,
            'forma_pago' => $item->forma_pago_snapshot,
            'forma_pago_label' => PedidoBmaPago::labelForma($item->forma_pago_snapshot),
            'banco' => $item->banco_snapshot,
            'banco_id' => $item->catalogo_banco_id,
            'referencia' => $item->referencia_snapshot,
            'fecha_pago' => $fechas['pago'],
            'fecha_pago_label' => $fechas['pago_label'],
            'capturado_at' => $fechas['reportada'],
            'capturado_at_label' => $fechas['reportada_label'],
            'validado_at' => $fechas['validacion'],
            'validado_at_label' => $fechas['validacion_label'],
            'estado_revision' => $estadoRevision,
            'estado_validacion_label' => $this->labelEstadoValidacion($estadoRevision),
            'reportado_por' => $item->capturadoPor?->name,
            'validado_por' => $item->revisadoPor?->name ?? $cierre->validadoPor?->name,
            'observaciones' => $this->observaciones($item, $estadoRevision),
            'reportado_posteriormente' => $fechas['reportado_posteriormente'],
            'posible_duplicado' => isset($posiblesDuplicados[$item->id]),
            'sin_voucher' => ! $tieneVoucher,
            'con_saf_relacionado' => $saf > 0.005,
            'fecha_corregida' => false,
            'clasificacion_ingreso' => $clasificacion,
            'clasificacion_ingreso_label' => ClasificacionIngresoBancario::labelClasificacion($clasificacion),
            'cuenta_ingreso_bancario' => ClasificacionIngresoBancario::cuentaIngresoBancario($item),
            'activo_para_cobertura' => $item->activo_para_cobertura_snapshot,
            'cierre_id' => $cierre->id,
            'pedido_bma_id' => $cierre->pedido_bma_id,
            'admin_pedido_error' => $cierre->tieneErrorAdminPedido(),
            ...AdminEstadoReportePagosPedidos::payloadItem($item),
            'evidencia' => ($puedeEvidencia && $tieneVoucher) ? [
                'nombre' => $item->nombre_archivo_snapshot,
                'mime_type' => $item->mime_type_snapshot,
                'tamano_bytes' => $item->tamano_bytes_snapshot,
                'url' => route('reportes.pagos_pedidos.evidencia_pago', ['pago' => $item->pedido_bma_pago_id]),
            ] : null,
        ];
    }

    private function labelEstadoValidacion(string $estado): string
    {
        return match ($estado) {
            PedidoBmaPago::REVISION_PENDIENTE, PedidoBmaPago::REVISION_EN_REVISION => 'Pendiente',
            PedidoBmaPago::REVISION_VERIFICADO => 'Validado',
            PedidoBmaPago::REVISION_RECHAZADO => 'Rechazado',
            PedidoBmaPago::REVISION_CON_OBSERVACIONES => 'Con observaciones',
            default => $estado !== '' ? ucfirst(str_replace('_', ' ', $estado)) : '—',
        };
    }

    private function observaciones(PedidoBmaCierrePagoItem $item, string $estado): ?string
    {
        if ($item->motivo_rechazo_snapshot) {
            return $item->motivo_rechazo_snapshot;
        }

        if ($estado === PedidoBmaPago::REVISION_CON_OBSERVACIONES) {
            return 'Con observaciones en revisión';
        }

        return null;
    }
}
