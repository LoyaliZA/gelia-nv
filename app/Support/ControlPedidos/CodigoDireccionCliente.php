<?php

namespace App\Support\ControlPedidos;

/**
 * Código visual de dirección: 8699 (1.ª), 8699-1 (2.ª), 8699-2 (3.ª)…
 */
class CodigoDireccionCliente
{
    public static function formatear(?string $numeroCliente, int|string|null $numeroDireccion): string
    {
        $cliente = trim((string) $numeroCliente);
        if ($cliente === '') {
            return '';
        }

        $n = (int) $numeroDireccion;
        if ($n <= 1) {
            return $cliente;
        }

        return $cliente.'-'.($n - 1);
    }
}
