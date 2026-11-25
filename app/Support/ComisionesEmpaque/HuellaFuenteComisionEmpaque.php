<?php

namespace App\Support\ComisionesEmpaque;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Support\ControlPedidos\ConsultaMunicipal;

class HuellaFuenteComisionEmpaque
{
    public static function de(int|string $cantidad, mixed $empacadoAt, mixed $canceladoAt, mixed $tipoId): string
    {
        return hash('sha256', json_encode([
            'cantidad' => (int) $cantidad,
            'empacado_at' => (string) $empacadoAt,
            'cancelado' => (string) $canceladoAt,
            'tipo' => $tipoId ?: null,
        ]));
    }

    public static function empaqueMunicipalEnTienda(PedidoBma $pedido): bool
    {
        return ConsultaMunicipal::es($pedido) && (ConsultaMunicipal::seguimiento($pedido)['area'] ?? null) === 'tienda';
    }

    public static function datosComerciales(PedidoBma $pedido, ?PedidoBmaCaja $caja = null, ?PedidoBmaCumplimientoFisico $cumplimiento = null): array
    {
        $remisiones = $caja?->remisionesActivas->map(fn ($remision) => [
            'folio' => $remision->folio_externo ?: ($remision->pedido?->numero_remision ?: $remision->pedido?->folio_remision),
            'pedido_id' => $remision->pedido_bma_id,
            'evidencia_id' => $remision->evidencia_documento_id,
        ])->sortBy(fn ($remision) => json_encode($remision))->values()->all() ?? [];
        if ($remisiones === [] && ($folio = $pedido->numero_remision ?: $pedido->folio_remision)) {
            $remisiones[] = ['folio' => $folio, 'pedido_id' => $pedido->id, 'evidencia_id' => null];
        }

        $motivo = match (true) {
            (bool) $pedido->envio_por_cobrar, $cumplimiento?->tarea?->modalidad_cobro === PedidoBmaCaratula::COBRO_POR_COBRAR => 'por_cobrar',
            (bool) $pedido->cliente_proporciona_guia && filled($caja?->numero_rastreo ?: $pedido->numero_rastreo) => 'guia_cliente',
            default => 'sin_informacion',
        };
        $modalidad = $pedido->solicitud_preparacion_json['modalidad_entrega'] ?? null;

        return [
            'remisiones' => $remisiones,
            'folios_remision' => collect($remisiones)->pluck('folio')->filter()->unique()->implode(' · '),
            'motivo_fuente' => $motivo,
            'modalidad_envio' => $modalidad,
            'huella_comercial' => hash('sha256', json_encode([
                $remisiones, $motivo, $modalidad, $pedido->cliente_id,
                $cumplimiento?->tarea?->catalogo_modalidad_preparacion_id,
                $cumplimiento?->tarea?->catalogo_paqueteria_id ?: $pedido->catalogo_paqueteria_id,
                $caja?->catalogo_tipo_guia_id ?: $pedido->catalogo_tipo_guia_id,
                $pedido->numero_rastreo, $caja?->numero_rastreo,
            ])),
        ];
    }

    /**
     * Hecho revisado de una cancelación. No es la huella de la captura manual:
     * esa describe el registro y no cambia cuando el pedido se cancela otra vez.
     */
    public static function deCancelacion(int $pedidoId, mixed $canceladoAt): string
    {
        return hash('sha256', json_encode([
            'hecho' => 'cancelacion',
            'pedido_bma_id' => $pedidoId,
            'cancelado_at' => (string) $canceladoAt,
        ]));
    }
}
