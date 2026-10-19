<?php

namespace App\Services\Listados;

use App\Models\CatalogoListaDescuento;
use Illuminate\Support\Facades\DB;

class PorcentajesListadoService
{
    /** Clave en gelia_settings => columna del Excel. El orden no empareja nombres. */
    public const COLUMNA_POR_SETTING = [
        'pct_bronce' => 'bronce',
        'pct_plata' => 'plata',
        'pct_oro' => 'oro',
        'pct_diamante' => 'diamante',
        'pct_plataformas' => 'plataformas',
        'pct_tiendanube' => 'tiendanube',
        'pct_tiendanubemayoreo' => 'tiendanubemayoreo',
    ];

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
     * Clave de gelia_settings para una lista, o null si no participa en listados.
     * PLATAFORMAS va antes que PLATA: el nombre contiene ambas palabras.
     */
    public static function settingKeyParaLista(string $nombreLista): ?string
    {
        $nombreUpper = strtoupper($nombreLista);

        if (str_contains($nombreUpper, 'TIENDANUBE')) {
            return str_contains($nombreUpper, 'MAYOREO')
                ? 'pct_tiendanubemayoreo'
                : 'pct_tiendanube';
        }

        if (str_contains($nombreUpper, 'PLATAFORMAS')) {
            return 'pct_plataformas';
        }

        foreach ([
            'BRONCE' => 'pct_bronce',
            'DIAMANTE' => 'pct_diamante',
            'ORO' => 'pct_oro',
            'PLATA' => 'pct_plata',
        ] as $keyword => $settingKey) {
            if (str_contains($nombreUpper, $keyword)) {
                return $settingKey;
            }
        }

        return null;
    }

    /**
     * Copia al catálogo solo las claves presentes en $settings.
     * No pisa created_at de una fila que ya existe.
     */
    public function sincronizarSettingsACatalogo(array $settings): void
    {
        $listas = CatalogoListaDescuento::query()->where('activo', true)->get(['id', 'nombre']);

        foreach ($listas as $lista) {
            $settingKey = self::settingKeyParaLista($lista->nombre);
            if ($settingKey === null || !array_key_exists($settingKey, $settings) || $settings[$settingKey] === '' || $settings[$settingKey] === null) {
                continue;
            }

            $this->guardarPorcentajeCatalogo((int) $lista->id, (float) $settings[$settingKey]);
        }
    }

    public function sincronizarCatalogoASettings(CatalogoListaDescuento $lista, float $porcentaje): void
    {
        $settingKey = self::settingKeyParaLista($lista->nombre);
        if ($settingKey === null) {
            return;
        }

        DB::table('gelia_settings')->updateOrInsert(
            ['key' => $settingKey],
            [
                'value' => $porcentaje,
                'created_at' => DB::raw('IFNULL(created_at, NOW())'),
                'updated_at' => now(),
            ]
        );
    }

    private function guardarPorcentajeCatalogo(int $listaId, float $porcentaje): void
    {
        $existe = DB::table('catalogo_porcentajes_listado_lista')
            ->where('catalogo_lista_descuento_id', $listaId)
            ->exists();

        if ($existe) {
            DB::table('catalogo_porcentajes_listado_lista')
                ->where('catalogo_lista_descuento_id', $listaId)
                ->update([
                    'porcentaje_descuento' => $porcentaje,
                    'activo' => true,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('catalogo_porcentajes_listado_lista')->insert([
            'catalogo_lista_descuento_id' => $listaId,
            'porcentaje_descuento' => $porcentaje,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
            'lista3'             => (float) ($settings['pct_lista3'] ?? 14.28),
            'lista4'             => (float) ($settings['pct_lista4'] ?? 17.71),
            'venta_especial'     => (float) ($settings['pct_venta_especial'] ?? 25.00),
            'boutique'           => (float) ($settings['pct_boutique'] ?? 25.00),
            'tiendanube'         => (float) ($settings['pct_tiendanube'] ?? 17.65),
            'tiendanubemayoreo'  => (float) ($settings['pct_tiendanubemayoreo'] ?? 22.00),
        ];

        $listas = CatalogoListaDescuento::with('porcentajeListado')->where('activo', true)->get();

        foreach ($listas as $lista) {
            $pct = $lista->porcentajeListado;
            if (!$pct || !$pct->activo) {
                continue;
            }

            $settingKey = self::settingKeyParaLista($lista->nombre);
            $columna = $settingKey === null ? null : (self::COLUMNA_POR_SETTING[$settingKey] ?? null);
            if ($columna === null) {
                continue;
            }

            $porcentajes[$columna] = (float) $pct->porcentaje_descuento;
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
            'lista3'             => 1 - ($porcentajes['lista3'] / 100),
            'lista4'             => 1 - ($porcentajes['lista4'] / 100),
            'venta_especial'     => 1 - ($porcentajes['venta_especial'] / 100),
            'boutique'           => 1 - ($porcentajes['boutique'] / 100),
            'tiendanube'         => 1 - ($porcentajes['tiendanube'] / 100),
            'tiendanubemayoreo'  => 1 - ($porcentajes['tiendanubemayoreo'] / 100),
            'divisor_costo'  => 1.3827,
            ...$meli,
        ];
    }
}
