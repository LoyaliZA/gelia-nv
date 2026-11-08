<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\User;

/** Reglas de cambio de cobertura; la autorización no revierte hechos financieros ni físicos. */
final class CambioCoberturaTienda
{
    public const PERMISO = 'control_pedidos.tienda.autorizar_cambio_cobertura';

    public static function version(PedidoBma $pedido): string
    {
        $json = app(\App\Services\ControlPedidos\FusionarSolicitudPreparacionPedido::class)->normalizar($pedido->solicitud_preparacion_json) ?? [];
        unset($json['cambio_cobertura_tienda'], $json['historial_cambios_cobertura']);

        return hash('sha256', json_encode([
            $json, $pedido->only(['total_mercancia', 'saldo_a_favor', 'total_a_cobrar', 'costo_envio', 'costo_seguro', 'aplica_seguro', 'cliente_id', 'almacen_id', 'sucursal_destino_id']), $pedido->revision_registro, $pedido->catalogo_estatus_pedido_id,
            $pedido->consulta_cerrada_at, $pedido->cancelado_at, $pedido->numero_remision,
            $pedido->pago_validado_at, $pedido->empacado_at,
            $pedido->pagosExhibicion()->orderBy('id')->get()->toArray(),
            $pedido->tareasPreparacion()->with('cumplimientoFisico')->orderBy('id')->get()->toArray(),
            $pedido->documentos()->orderBy('id')->get()->toArray(),
            $pedido->resguardosPdv()->orderBy('id')->get()->toArray(),
        ], JSON_THROW_ON_ERROR));
    }

    public static function bloqueo(PedidoBma $pedido): ?string
    {
        $json = $pedido->solicitud_preparacion_json ?? [];
        if ($pedido->cancelado_at || ! in_array($pedido->estatus?->fase_ciclo, ['BORRADOR', 'PESAJE_PENDIENTE', 'PESAJE_RESPONDIDO', 'RECHAZADO_VENDEDORA'], true)) {
            return 'El pedido ya avanzó de etapa o está cerrado. Requiere regularizar su proceso antes de cambiar la modalidad.';
        }
        if ((float) $pedido->saldo_a_favor > 0 || $pedido->pago_validado_at || $pedido->pagosExhibicion()->exists() || $pedido->tieneRemision() || filled($pedido->numero_remision) || ! empty($json['credito_wizerp']) || ! empty($json['tienda_md_reporte'])) {
            return 'Hay pagos, saldo a favor, crédito, reporte de Mismo día o remisión. Deben regularizarse por su proceso correspondiente; la autorización no los elimina ni los convierte.';
        }
        foreach (['compromisos_inventario', 'anexo_integracion', 'entrega_custodia', 'complemento_pdv', 'retiro_recoleccion', 'remision_tienda'] as $clave) {
            if (! empty($json[$clave])) return 'Hay compromisos o documentos del recorrido. Regularice su proceso antes de aplicar otra modalidad.';
        }
        if ($pedido->empacado_at || $pedido->resguardosPdv()->exists() || ! empty($json['transferencia_tienda']) || ! empty($json['segundo_traslado']) || ! empty($json['cambio_modalidad_tienda'])) {
            return 'Hay preparación, custodia o movimientos registrados. Deben regularizarse antes de aplicar otra modalidad.';
        }
        $instrucciones = InstruccionEntrega::abiertas((int) $pedido->id)->load('cumplimientoFisico');
        if ($instrucciones->count() > 1) return 'Hay más de una instrucción vigente. Regularice las instrucciones antes del cambio.';
        foreach ($instrucciones as $tarea) {
            $fisico = $tarea->cumplimientoFisico;
            if (! in_array($tarea->estado, ['PENDIENTE', 'EN_ATENCION', 'RESPONDIDA'], true)
                || ($fisico && ($fisico->estado !== PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR || $fisico->pago_confirmado_at || $fisico->salida_autorizada_at || filled($fisico->condicion_cobro) || $fisico->entregada_at || $fisico->empacado_at || $fisico->despachada_at))) {
                return 'La mercancía ya tiene un compromiso físico. Regularice el apartado o movimiento antes de aplicar otra modalidad.';
            }
        }
        if (! $pedido->consultaCerrada() || $pedido->motivoBloqueoCierreConsultaTienda() || $pedido->consulta_actualizacion_pendiente) {
            return 'Cierre la consulta vigente y resuelva sus ajustes antes de aplicar el cambio.';
        }

        return null;
    }

    public static function estado(PedidoBma $pedido, User $actor): array
    {
        $json = $pedido->solicitud_preparacion_json ?? [];
        $pendiente = $json['cambio_cobertura_tienda'] ?? null;

        return [
            'version' => self::version($pedido),
            'guardada' => ! empty($json['cobertura_tienda']['tipo']),
            'bloqueo_aplicacion' => self::bloqueo($pedido),
            'solicitud' => $pendiente,
            'historial' => $json['historial_cambios_cobertura'] ?? [],
            'puede_solicitar' => $actor->can('control_pedidos.crear') && VisibilidadPedidoBma::puedeMutarComoVendedora($actor, $pedido) && ! $pedido->cancelado_at && $pedido->estatus?->fase_ciclo !== 'ENTREGADO',
            'puede_autorizar' => $actor->can(self::PERMISO) && (int) ($pendiente['solicitada_por_id'] ?? 0) !== (int) $actor->id,
        ];
    }
}
