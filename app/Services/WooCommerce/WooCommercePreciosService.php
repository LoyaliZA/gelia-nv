<?php

namespace App\Services\WooCommerce;

use App\Models\Woocommerce\WoocommerceConfiguracion;
use App\Models\Woocommerce\WoocommerceMargin;
use App\Models\Woocommerce\WoocommerceProduct;
use Rap2hpoutre\FastExcel\FastExcel;

class WooCommercePreciosService
{
    /** Columnas de precio base aceptadas (prioridad). PG = Lista de Resurtido / Wizerp. */
    private const COLUMNAS_PRECIO_BASE = ['pg', 'costowizerp', 'costocalculado'];

    public function obtenerIva(): float
    {
        return (float) (WoocommerceConfiguracion::obtener()->iva ?? 1.16);
    }

    /**
     * Extrae SKU → precio base desde Excel.
     *
     * Formatos soportados:
     * 1) Lista de Resurtido (cabeceras): Folio, SKU, Descripcion, Existencia, PG, Plataformas, Bronce → usa PG
     * 2) Export Wizerp sin cabecera: col. B = SKU, col. F = precio base
     */
    public function extraerPreciosDesdeExcel(string $rutaArchivo): array
    {
        $precios = [];

        (new FastExcel)->import($rutaArchivo, function ($linea) use (&$precios) {
            $normalizada = $this->normalizarFila($linea);
            $sku = $this->extraerSku($normalizada);
            $precio = $this->extraerPrecioBase($normalizada);

            if ($sku !== '' && $precio > 0) {
                $precios[$sku] = $precio;
            }
        });

        if (!empty($precios)) {
            return $precios;
        }

        // Fallback: export crudo Wizerp (sin fila de cabecera)
        (new FastExcel)->withoutHeaders()->import($rutaArchivo, function ($linea) use (&$precios) {
            $sku = trim((string) ($linea[1] ?? ''));
            $precio = $this->parsePrecioNumerico($linea[5] ?? 0);

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
            $precioBase = $this->resolverPrecioPorSku($preciosWizerp, $prod->sku);
            if ($precioBase === null) {
                continue;
            }

            $normal = $this->calcular($precioBase, 'normal', $margenes, $iva);
            $rebaja = $this->calcular($precioBase, 'rebaja', $margenes, $iva);

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

    /**
     * Cruce flexible de SKU (con/sin ceros a la izquierda).
     */
    public function resolverPrecioPorSku(array $precios, string $skuCatalogo): ?float
    {
        $sku = trim($skuCatalogo);
        if ($sku === '') {
            return null;
        }

        if (isset($precios[$sku])) {
            return $precios[$sku];
        }

        $sinCeros = ltrim($sku, '0');
        if ($sinCeros !== '' && isset($precios[$sinCeros])) {
            return $precios[$sinCeros];
        }

        foreach ($precios as $clave => $valor) {
            if (ltrim((string) $clave, '0') === $sinCeros) {
                return $valor;
            }
        }

        return null;
    }

    private function normalizarFila(array $linea): array
    {
        $normalizada = [];
        foreach ($linea as $clave => $valor) {
            $normalizada[$this->normalizarClaveColumna((string) $clave)] = $valor;
        }

        return $normalizada;
    }

    private function normalizarClaveColumna(string $clave): string
    {
        $clave = mb_strtolower(trim($clave));
        $clave = str_replace([' ', '_', '-'], '', $clave);
        $clave = strtr($clave, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);

        return $clave;
    }

    private function extraerSku(array $linea): string
    {
        $sku = $linea['sku'] ?? '';

        return trim((string) $sku);
    }

    private function extraerPrecioBase(array $linea): float
    {
        foreach (self::COLUMNAS_PRECIO_BASE as $columna) {
            if (array_key_exists($columna, $linea)) {
                $precio = $this->parsePrecioNumerico($linea[$columna]);
                if ($precio > 0) {
                    return $precio;
                }
            }
        }

        return 0.0;
    }

    private function parsePrecioNumerico(mixed $valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $limpio = str_replace(['$', ',', ' '], '', (string) $valor);

        return is_numeric($limpio) ? (float) $limpio : 0.0;
    }
}
