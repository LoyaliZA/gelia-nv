<?php

namespace App\Support\ControlPedidos\RegistrarPedidos;

/** La captura inicial no cambia la modalidad de un pedido que ya avanzó. */
final class LimiteModalidadCapturaPedido
{
    public const MENSAJE = 'Este pedido ya tiene una instrucción, un pago o una remisión. La modalidad se conserva.';

    /**
     * @param  array<string, mixed>  $actual
     * @param  array<string, mixed>  $entrada
     */
    public static function cambia(array $actual, array $entrada): bool
    {
        foreach (['modalidad_entrega', 'codigo_modalidad'] as $clave) {
            if (! array_key_exists($clave, $entrada)) {
                continue;
            }
            if (self::texto($entrada[$clave]) !== self::texto($actual[$clave] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $actual
     * @param  array<string, mixed>  $entrada
     */
    public static function bloqueado(bool $avanzado, ?array $actual, array $entrada): ?string
    {
        if (! $avanzado || ! self::cambia($actual ?? [], $entrada)) {
            return null;
        }

        return self::MENSAJE;
    }

    private static function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
