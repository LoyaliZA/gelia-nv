<?php

namespace App\Services\Almacenes;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\ProductoVentaAlmacen;

class ProcesarFilaVentaImportacionService
{
    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, string>  $mapping
     * @return array{ok: bool, error?: string}
     */
    public function ejecutar(array $fila, array $mapping, ?int $almacenIdFijo = null): array
    {
        $skuRaw = trim((string) ($fila[$mapping['sku'] ?? ''] ?? ''));
        if ($skuRaw === '') {
            return ['ok' => false, 'error' => 'SKU vacío'];
        }
        $sku = Producto::normalizarSku($skuRaw);
        $producto = Producto::query()->where('sku', $sku)->first();
        if (! $producto) {
            return ['ok' => false, 'error' => "SKU no encontrado: {$sku}"];
        }

        $almacenId = $almacenIdFijo;
        if (! $almacenId) {
            $codigo = trim((string) ($fila[$mapping['codigo_almacen'] ?? ''] ?? ''));
            if ($codigo === '') {
                return ['ok' => false, 'error' => 'Falta codigo_almacen'];
            }
            $almacen = Almacen::query()->where('codigo', $codigo)->first();
            if (! $almacen) {
                return ['ok' => false, 'error' => "Almacén no encontrado: {$codigo}"];
            }
            $almacenId = (int) $almacen->id;
        }

        $periodo = trim((string) ($fila[$mapping['periodo'] ?? ''] ?? ''));
        if (! preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            return ['ok' => false, 'error' => "Periodo inválido (YYYY-MM): {$periodo}"];
        }

        $monto = (float) str_replace(',', '', (string) ($fila[$mapping['monto_venta'] ?? ''] ?? 0));
        $cantidad = null;
        if (! empty($mapping['cantidad_vendida']) && isset($fila[$mapping['cantidad_vendida']])) {
            $cantidad = (float) str_replace(',', '', (string) $fila[$mapping['cantidad_vendida']]);
        }

        ProductoVentaAlmacen::query()->updateOrCreate(
            [
                'producto_id' => $producto->id,
                'almacen_id' => $almacenId,
                'periodo' => $periodo,
            ],
            [
                'monto_venta' => $monto,
                'cantidad_vendida' => $cantidad,
            ]
        );

        return ['ok' => true];
    }
}
