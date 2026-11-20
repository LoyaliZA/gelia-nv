<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\ControlPedidos\PedidoBma;

final class GeneradorFolioBultoResguardoPdv
{
    public static function desdeNumero(PedidoBma $pedido, int $numero): string
    {
        // Las bolsas de custodia documental conservan el folio que usa la recepción de GELIA.
        if (\App\Support\ControlPedidos\CustodiaDocumentalTienda::aplica($pedido)) {
            return 'BOLSA-'.$pedido->id.'-'.$numero;
        }

        $base = trim((string) ($pedido->folio_remision ?: $pedido->folio ?: 'BULTO'));

        return "{$base}-B{$numero}";
    }
}
