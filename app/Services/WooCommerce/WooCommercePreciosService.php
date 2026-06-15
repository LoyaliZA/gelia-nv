<?php

namespace App\Services\WooCommerce;

use App\Models\Woocommerce\WoocommerceConfiguracion;
use App\Models\Woocommerce\WoocommerceMargin;
use App\Models\Woocommerce\WoocommerceProduct;
use Rap2hpoutre\FastExcel\FastExcel;

class WooCommercePreciosService
{
    public function obtenerIva(): float
    {
        return (float) (WoocommerceConfiguracion::obtener()->iva ?? 1.16);
    }

    public function extraerPreciosDesdeExcel(string $rutaArchivo): array
    {
        $precios = [];
        (new FastExcel)->import($rutaArchivo, function ($linea) use (&$precios) {
            $linea = array_change_key_case($linea, CASE_LOWER);
            $sku = trim((string) ($linea['sku'] ?? ''));
            $precio = (float) ($linea['plataforma'] ?? 0);
            if ($sku !== '' && $precio > 0) {
                $precios[$sku] = $precio;
            }
        });

        return $precios;
    }

    public function calcular(float $base, string $tipo, $margenes, float $iva): float
    {
        $mult = 1.0;
        foreach ($margenes as $m) {
            if ($base >= $m->precio_min && $base <= $m->precio_max) {
                $mult = ($tipo === 'rebaja') ? $m->multiplicador_rebaja : $m->multiplicador_normal;
                break;
            }
        }

        return round(($base * $mult) / $iva, 2);
    }

    public function generarAnalisisDeCambios(array $preciosWizerp, ?float $iva = null, $margenes = null): array
    {
        $iva = $iva ?? $this->obtenerIva();
        $margenes = $margenes ?? WoocommerceMargin::orderBy('precio_min')->get();
        $cambios = [];

        foreach (WoocommerceProduct::all() as $prod) {
            if (!isset($preciosWizerp[$prod->sku])) {
                continue;
            }

            $base = $preciosWizerp[$prod->sku];
            $normal = $this->calcular($base, 'normal', $margenes, $iva);
            $rebaja = $this->calcular($base, 'rebaja', $margenes, $iva);

            if ($prod->precio_normal != $normal || $prod->precio_rebajado != $rebaja) {
                $cambios[] = [
                    'sku' => $prod->sku,
                    'nombre' => $prod->nombre,
                    'precio_normal_anterior' => $prod->precio_normal,
                    'precio_normal_nuevo' => $normal,
                    'precio_rebaja_anterior' => $prod->precio_rebajado,
                    'precio_rebaja_nuevo' => $rebaja,
                ];
            }
        }

        return $cambios;
    }
}
