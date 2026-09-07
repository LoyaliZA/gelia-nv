<?php

namespace App\Support\PuntoVenta\Reportes;

final class MetricaResguardoPdvIds
{
    public const R_01_PENDIENTES_RECIBIR = 'R-01';

    public const R_02_EN_CUSTODIA = 'R-02';

    public const R_03_INCIDENCIAS_ABIERTAS = 'R-03';

    public const R_04_REZAGADOS = 'R-04';

    public const R_05_PROXIMOS_VENCER = 'R-05';

    public const R_06_VENCIDOS = 'R-06';

    public const R_07_TIEMPO_RECEPCION = 'R-07';

    public const R_08_TIEMPO_CUSTODIA = 'R-08';

    public const R_09_TASA_ENTREGA = 'R-09';

    public const R_10_TASA_INCIDENCIA = 'R-10';

    public const R_11_RECEPCIONES_RANGO = 'R-11';

    public const R_12_DEVOLUCIONES_RANGO = 'R-12';

    public const UMBRAL_PERCENTILES = 30;

    /**
     * @return list<string>
     */
    public static function todas(): array
    {
        return [
            self::R_01_PENDIENTES_RECIBIR,
            self::R_02_EN_CUSTODIA,
            self::R_03_INCIDENCIAS_ABIERTAS,
            self::R_04_REZAGADOS,
            self::R_05_PROXIMOS_VENCER,
            self::R_06_VENCIDOS,
            self::R_07_TIEMPO_RECEPCION,
            self::R_08_TIEMPO_CUSTODIA,
            self::R_09_TASA_ENTREGA,
            self::R_10_TASA_INCIDENCIA,
            self::R_11_RECEPCIONES_RANGO,
            self::R_12_DEVOLUCIONES_RANGO,
        ];
    }
}
