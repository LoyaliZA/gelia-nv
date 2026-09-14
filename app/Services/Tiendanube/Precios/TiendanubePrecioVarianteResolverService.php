<?php

namespace App\Services\Tiendanube\Precios;

use App\Models\Tiendanube\TiendanubeProductoVariante;

class TiendanubePrecioVarianteResolverService
{
    public const ESTADO_ENCONTRADO = 'encontrado';

    public const ESTADO_NO_ENCONTRADO = 'no_encontrado';

    public const ESTADO_AMBIGUO = 'ambiguo';

    /**
     * Comparación exacta por string (preserva ceros iniciales). Solo se recorta el input.
     * Varias variantes con el mismo SKU (mismo o distinto producto) son ambiguas.
     *
     * @return array{
     *     estado: string,
     *     sku: string,
     *     variante_id: int|null,
     *     producto_id: int|null,
     *     candidatos: list<array{variante_id: int, producto_id: int, sku: string|null, nombre: string}>
     * }
     */
    public function resolverPorSku(string $sku): array
    {
        $sku = trim($sku);

        if ($sku === '') {
            return [
                'estado' => self::ESTADO_NO_ENCONTRADO,
                'sku' => '',
                'variante_id' => null,
                'producto_id' => null,
                'candidatos' => [],
            ];
        }

        $variantes = TiendanubeProductoVariante::query()
            ->with('producto')
            ->where('sku', $sku)
            ->orderBy('id')
            ->get();

        $candidatos = [];
        foreach ($variantes as $variante) {
            if (! $variante->producto) {
                continue;
            }
            $candidatos[] = [
                'variante_id' => (int) $variante->id,
                'producto_id' => (int) $variante->producto_id,
                'sku' => $variante->sku,
                'nombre' => $variante->producto->nombreVisible(),
            ];
        }

        if ($candidatos === []) {
            return [
                'estado' => self::ESTADO_NO_ENCONTRADO,
                'sku' => $sku,
                'variante_id' => null,
                'producto_id' => null,
                'candidatos' => [],
            ];
        }

        if (count($candidatos) === 1) {
            return [
                'estado' => self::ESTADO_ENCONTRADO,
                'sku' => $sku,
                'variante_id' => $candidatos[0]['variante_id'],
                'producto_id' => $candidatos[0]['producto_id'],
                'candidatos' => $candidatos,
            ];
        }

        return [
            'estado' => self::ESTADO_AMBIGUO,
            'sku' => $sku,
            'variante_id' => null,
            'producto_id' => null,
            'candidatos' => $candidatos,
        ];
    }

    /**
     * @return array{
     *     estado: string,
     *     sku: string,
     *     variante_id: int|null,
     *     producto_id: int|null,
     *     candidatos: list<array{variante_id: int, producto_id: int, sku: string|null, nombre: string}>
     * }
     */
    public function resolverPorVarianteId(int $varianteId): array
    {
        $variante = TiendanubeProductoVariante::query()->with('producto')->find($varianteId);
        if (! $variante || ! $variante->producto) {
            return [
                'estado' => self::ESTADO_NO_ENCONTRADO,
                'sku' => '',
                'variante_id' => null,
                'producto_id' => null,
                'candidatos' => [],
            ];
        }

        $candidato = [
            'variante_id' => (int) $variante->id,
            'producto_id' => (int) $variante->producto_id,
            'sku' => $variante->sku,
            'nombre' => $variante->producto->nombreVisible(),
        ];

        return [
            'estado' => self::ESTADO_ENCONTRADO,
            'sku' => (string) ($variante->sku ?? ''),
            'variante_id' => (int) $variante->id,
            'producto_id' => (int) $variante->producto_id,
            'candidatos' => [$candidato],
        ];
    }
}
