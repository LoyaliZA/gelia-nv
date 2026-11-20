<?php

namespace App\Support\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;
use App\Services\SaldosAFavor\CoberturaPagoPedidoBmaService;

final class CoberturaEnvioNacional
{
    public static function requiereCotizacion(PedidoBma $pedido): bool
    {
        $json = $pedido->solicitud_preparacion_json ?? [];

        return CapturaNacional::es($pedido)
            && ! $pedido->tienePaqueteriaPendiente()
            && (isset($json['pago_solo_mercancia_nacional']) || ($json['cambio_paqueteria_cobertura'] ?? null) === 'reabierta')
            && (int) ($json['cotizacion_nacional']['paqueteria_id'] ?? 0) !== (int) $pedido->catalogo_paqueteria_id;
    }

    public static function motivoBloqueoDespacho(PedidoBma $pedido): ?string
    {
        if (! CapturaNacional::es($pedido)) {
            return null;
        }
        if ($pedido->tienePaqueteriaPendiente()) {
            return 'Elija la paquetería antes de generar la guía o despachar.';
        }
        $json = $pedido->solicitud_preparacion_json;
        if (self::requiereCotizacion($pedido)) {
            return 'Guarde la cotización vigente antes de continuar con el envío.';
        }

        if ((isset($json['cotizacion_nacional']) || isset($json['pago_solo_mercancia_nacional']))
            && (! $pedido->pago_validado_at || ! app(CoberturaPagoPedidoBmaService::class)->calcular($pedido)['cubierto'])) {
            return 'El envío está pendiente de cobertura. Verifique los cargos y el pago antes de continuar.';
        }

        return null;
    }

    public static function assertDespacho(PedidoBma $pedido): void
    {
        if ($motivo = self::motivoBloqueoDespacho($pedido)) {
            throw new \RuntimeException($motivo);
        }
    }

    public static function assertRemision(PedidoBma $pedido): void
    {
        if ($pedido->tienePaqueteriaNacionalPendiente()) {
            if (! $pedido->pago_validado_at) {
                throw new \RuntimeException('Verifique el pago de mercancía antes de registrar su remisión.');
            }
            return;
        }

        self::assertDespacho($pedido);
    }
}
