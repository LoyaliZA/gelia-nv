<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\PlataformaPago;

class CalcularComisionPlataformaService
{
    /**
     * @return array{comision_base: float, comision_iva: float, comision_total: float}
     */
    public function ejecutar(float $precioVenta, PlataformaPago $plataforma): array
    {
        $tasaComision = (float) $plataforma->tasa_comision_pct / 100;
        $tasaIva = (float) $plataforma->tasa_iva_pct / 100;

        $comisionBase = round(($precioVenta * $tasaComision) + (float) $plataforma->cuota_fija, 2);
        $comisionIva = round($comisionBase * $tasaIva, 2);
        $comisionTotal = round($comisionBase + $comisionIva, 2);

        return [
            'comision_base' => $comisionBase,
            'comision_iva' => $comisionIva,
            'comision_total' => $comisionTotal,
        ];
    }
}
