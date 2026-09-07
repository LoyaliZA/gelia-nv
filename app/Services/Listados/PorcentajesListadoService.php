<?php

namespace App\Services\Listados;

use App\Models\CatalogoListaDescuento;
use Illuminate\Support\Facades\DB;

class PorcentajesListadoService
{
    public const MELI_DEFAULTS = [
        'meli_full_fijo_1' => 45.0,
        'meli_full_fijo_2' => 90.0,
        'meli_msi_fijo_1' => 90.0,
        'meli_msi_fijo_2' => 90.0,
        'meli_full_pct_1' => 14.0,
        'meli_full_pct_2' => 8.0,
        'meli_msi_pct_1' => 17.5,
        'meli_msi_pct_2' => 8.0,
        'meli_pct_iva' => 2.5,
        'meli_factor_iva' => 1.16,
    ];

    /**
     * Costo MELI = (Plataformas + fijo_1 + fijo_2) / (1 - pct_1% - pct_2% - pct_iva%/factor_iva)
     */
    public static function calcularCostoMeli(
        float $plataformas,
        float $fijo1,
        float $fijo2,
        float $pct1,
        float $pct2,
        float $pctIva,
        float $factorIva
    ): float {
        $numerador = $plataformas + $fijo1 + $fijo2;

        $denominador = 1
            - ($pct1 / 100)
            - ($pct2 / 100)
            - (($pctIva / 100) / $factorIva);

        if ($denominador <= 0) {
            return 0.0;
        }

        return $numerador / $denominador;
    }

    /**
     * Obtiene multiplicadores de precio para columnas de listados Excel.
     * Prioriza catálogo por lista; usa gelia_settings como respaldo para columnas auxiliares.
     */
    public function obtenerMultiplicadores(): array
    {
        $settings = DB::table('gelia_settings')->pluck('value', 'key');

        $porcentajes = [
            'bronce'         => (float) ($settings['pct_bronce'] ?? 12.39),
            'plata'          => (float) ($settings['pct_plata'] ?? 14.14),
            'oro'            => (float) ($settings['pct_oro'] ?? 15.89),
            'diamante'       => (float) ($settings['pct_diamante'] ?? 17.65),
            'plataformas'    => (float) ($settings['pct_plataformas'] ?? 23.00),
            'lista3'         => (float) ($settings['pct_lista3'] ?? 14.28),
            'lista4'         => (float) ($settings['pct_lista4'] ?? 17.71),
            'venta_especial' => (float) ($settings['pct_venta_especial'] ?? 25.00),
            'boutique'       => (float) ($settings['pct_boutique'] ?? 25.00),
        ];

        $mapaKeywords = [
            'bronce'   => 'BRONCE',
            'plata'    => 'PLATA',
            'oro'      => 'ORO',
            'diamante' => 'DIAMANTE',
        ];

        $listas = CatalogoListaDescuento::with('porcentajeListado')->where('activo', true)->get();

        foreach ($listas as $lista) {
            $pct = $lista->porcentajeListado;
            if (!$pct || !$pct->activo) {
                continue;
            }

            $nombreUpper = strtoupper($lista->nombre);
            foreach ($mapaKeywords as $key => $keyword) {
                if (str_contains($nombreUpper, $keyword)) {
                    $porcentajes[$key] = (float) $pct->porcentaje_descuento;
                    break;
                }
            }

            if (str_contains($nombreUpper, 'PLATAFORMAS')) {
                $porcentajes['plataformas'] = (float) $pct->porcentaje_descuento;
            }
        }

        $meli = [];
        foreach (self::MELI_DEFAULTS as $key => $default) {
            $meli[$key] = (float) ($settings[$key] ?? $default);
        }

        return [
            'bronce'         => 1 - ($porcentajes['bronce'] / 100),
            'plata'          => 1 - ($porcentajes['plata'] / 100),
            'oro'            => 1 - ($porcentajes['oro'] / 100),
            'diamante'       => 1 - ($porcentajes['diamante'] / 100),
            'plataformas'    => 1 - ($porcentajes['plataformas'] / 100),
            'lista3'         => 1 - ($porcentajes['lista3'] / 100),
            'lista4'         => 1 - ($porcentajes['lista4'] / 100),
            'venta_especial' => 1 - ($porcentajes['venta_especial'] / 100),
            'boutique'       => 1 - ($porcentajes['boutique'] / 100),
            'divisor_costo'  => 1.3827,
            ...$meli,
        ];
    }
}
