<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\ControlPedidos\PedidoBma;

final class GeneradorFolioBultoResguardoPdv
{
    public static function desdeNumero(PedidoBma $pedido, int $numero): string
    {
        $base = trim((string) ($pedido->folio_remision ?: $pedido->folio ?: 'BULTO'));

        return "{$base}-B{$numero}";
    }
}
