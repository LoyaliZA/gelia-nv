<?php

namespace App\Services\Tiendanube;

use App\Models\Tiendanube\TiendanubeProductoVariante;

class TiendanubeImageSkuResolverService
{
    public const ESTADO_ENCONTRADO = 'encontrado';

    public const ESTADO_NO_ENCONTRADO = 'no_encontrado';

    public const ESTADO_AMBIGUO = 'ambiguo';

    /**
     * Comparación exacta por string (preserva ceros iniciales). Solo se recorta el input.
     *
     * @return array{
     *     estado: string,
     *     sku: string,
     *     producto_id: int|null,
     *     candidatos: list<array{producto_id: int, nombre: string, variante_ids: list<int>, imagen_actual: string|null}>
     * }
     */
    public function resolver(string $sku): array
    {
        $sku = trim($sku);

        if ($sku === '') {
            return [
                'estado' => self::ESTADO_NO_ENCONTRADO,
                'sku' => '',
                'producto_id' => null,
                'candidatos' => [],
            ];
        }

        $variantes = TiendanubeProductoVariante::query()
            ->with(['producto.imagenes'])
            ->where('sku', $sku)
            ->orderBy('id')
            ->get();

        $porProducto = [];
        foreach ($variantes as $variante) {
            if (! $variante->producto) {
                continue;
            }
            $pid = (int) $variante->producto_id;
            if (! isset($porProducto[$pid])) {
                $producto = $variante->producto;
                $primera = $producto->imagenes->sortBy('position')->first();
                $porProducto[$pid] = [
                    'producto_id' => $pid,
                    'nombre' => $producto->nombreVisible(),
                    'variante_ids' => [],
                    'imagen_actual' => $primera?->src,
                ];
            }
            $porProducto[$pid]['variante_ids'][] = (int) $variante->id;
        }

        $candidatos = array_values($porProducto);

        if ($candidatos === []) {
            return [
                'estado' => self::ESTADO_NO_ENCONTRADO,
                'sku' => $sku,
                'producto_id' => null,
                'candidatos' => [],
            ];
        }

        if (count($candidatos) === 1) {
            return [
                'estado' => self::ESTADO_ENCONTRADO,
                'sku' => $sku,
                'producto_id' => $candidatos[0]['producto_id'],
                'candidatos' => $candidatos,
            ];
        }

        return [
            'estado' => self::ESTADO_AMBIGUO,
            'sku' => $sku,
            'producto_id' => null,
            'candidatos' => $candidatos,
        ];
    }
}
