<?php

namespace App\Services\Contabilidad;

class CalcularMontoEsperadoBancoService
{
    public function ejecutar(string $codigoTipoTransaccion, float $ventaTotal, float $comisionPlataforma): float
    {
        if (str_contains(strtolower($codigoTipoTransaccion), 'venta')) {
            return round($ventaTotal - $comisionPlataforma, 2);
        }

        return round(-abs($ventaTotal + $comisionPlataforma), 2);
    }
}
