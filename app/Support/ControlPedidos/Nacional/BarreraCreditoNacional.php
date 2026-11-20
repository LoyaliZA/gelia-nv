<?php

namespace App\Support\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;
use InvalidArgumentException;
use RuntimeException;

/** Crédito Wizerp de la captura nacional. No cancela al día siguiente: solo impide avanzar. */
final class BarreraCreditoNacional
{
    public static function bloqueado(PedidoBma $pedido): bool
    {
        if (! CapturaNacional::es($pedido)) {
            return false;
        }

        return self::resultado($pedido) === 'bloqueado';
    }

    public static function assertPuedeRemisionar(PedidoBma $pedido): void
    {
        if (self::bloqueado($pedido)) {
            throw new RuntimeException('El crédito bloqueado no tiene remisión.');
        }
    }

    public static function assertPuedeGuiar(PedidoBma $pedido): void
    {
        if (self::bloqueado($pedido)) {
            throw new RuntimeException('El crédito bloqueado no tiene guía.');
        }
    }

    public static function assertPuedeSalir(PedidoBma $pedido): void
    {
        if (self::bloqueado($pedido)) {
            throw new RuntimeException('El crédito bloqueado no tiene salida.');
        }
    }

    public static function assertFormaPago(PedidoBma $pedido, ?string $forma): void
    {
        if (! CapturaNacional::es($pedido) || $forma !== 'transferencia') {
            return;
        }
        if (in_array(self::resultado($pedido), ['aprobado', 'bloqueado'], true)) {
            throw new InvalidArgumentException('El crédito no se mezcla con una transferencia.');
        }
    }

    private static function resultado(PedidoBma $pedido): ?string
    {
        $json = $pedido->solicitud_preparacion_json;

        return is_array($json) ? ($json['credito_wizerp']['resultado'] ?? null) : null;
    }
}
