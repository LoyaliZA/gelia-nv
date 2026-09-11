<?php

namespace App\Services\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubeStockPayloadException;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\Tiendanube\TiendanubeUbicacion;
use App\Models\Tiendanube\TiendanubeVarianteNivel;

class TiendanubeStockPayload
{
    public const MODE_SET_LEVEL = 'set_level';

    public const MODE_SET_STOCK_MANAGEMENT = 'set_stock_management';

    public function __construct(
        public readonly int $storeId,
        public readonly int $productId,
        public readonly int $variantId,
        public readonly ?string $locationId,
        public readonly ?int $stock,
        public readonly bool $stockManagement,
        public readonly string $mode,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $stock = $data['stock'] ?? null;
        if ($stock === '') {
            $stock = null;
        } elseif ($stock !== null && is_numeric($stock)) {
            $stock = (int) $stock;
        } elseif ($stock !== null) {
            $stock = null;
        }

        return new self(
            storeId: (int) ($data['store_id'] ?? 0),
            productId: (int) ($data['product_id'] ?? 0),
            variantId: (int) ($data['variant_id'] ?? 0),
            locationId: isset($data['location_id']) && $data['location_id'] !== '' && $data['location_id'] !== null
                ? (string) $data['location_id']
                : null,
            stock: $stock,
            stockManagement: (bool) ($data['stock_management'] ?? true),
            mode: (string) ($data['mode'] ?? self::MODE_SET_LEVEL),
        );
    }

    public function validate(): void
    {
        if (! in_array($this->mode, [self::MODE_SET_LEVEL, self::MODE_SET_STOCK_MANAGEMENT], true)) {
            throw new TiendanubeStockPayloadException('modo_invalido', 'Modo de stock no reconocido.');
        }

        $config = TiendanubeConfiguracion::obtener();
        if (($config->locations_probe ?? null) !== 'ok') {
            throw new TiendanubeStockPayloadException(
                'inventario_no_disponible',
                'Location no disponible en esta tienda (probe distinto de ok).'
            );
        }

        $variante = TiendanubeProductoVariante::query()->find($this->variantId);
        if (! $variante || (int) $variante->producto_id !== $this->productId) {
            throw new TiendanubeStockPayloadException('variante_invalida', 'La variante no pertenece al producto.');
        }

        if ($this->mode === self::MODE_SET_LEVEL) {
            $this->assertUbicacionActiva();
            $this->assertEspejoReciente($variante, $config);
        }
    }

    /**
     * Body para PUT /products/{id}/variants/{id}. Nunca mezcla stock plano con niveles.
     *
     * @return array<string, mixed>
     */
    public function toVariantApiPayload(): array
    {
        if ($this->mode === self::MODE_SET_STOCK_MANAGEMENT) {
            return ['stock_management' => $this->stockManagement];
        }

        return [
            'inventory_levels' => [[
                'location_id' => (string) $this->locationId,
                'stock' => $this->stock,
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'store_id' => $this->storeId,
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'location_id' => $this->locationId,
            'stock' => $this->stock,
            'stock_management' => $this->stockManagement,
            'mode' => $this->mode,
        ];
    }

    private function assertUbicacionActiva(): void
    {
        $id = trim((string) $this->locationId);
        if ($id === '') {
            throw new TiendanubeStockPayloadException('ubicacion_invalida', 'Falta location_id.');
        }

        $ubicacion = TiendanubeUbicacion::query()->find($id);
        if (! $ubicacion || ! $ubicacion->activa || (int) $ubicacion->store_id !== $this->storeId) {
            throw new TiendanubeStockPayloadException('ubicacion_invalida', 'La ubicación no pertenece a esta tienda o no está activa.');
        }
    }

    private function assertEspejoReciente(TiendanubeProductoVariante $variante, TiendanubeConfiguracion $config): void
    {
        $ubicacion = TiendanubeUbicacion::query()->find((string) $this->locationId);
        if (! $ubicacion?->synced_at) {
            throw new TiendanubeStockPayloadException('espejo_desactualizado', 'La ubicación no tiene lectura reciente.');
        }

        if ($config->multi_inventario_activo) {
            $base = TiendanubeVarianteNivel::query()->where('variante_id', $variante->id);
            $tieneFilas = (clone $base)->exists();
            $tieneSync = (clone $base)->whereNotNull('synced_at')->exists();
            if ($tieneFilas && ! $tieneSync) {
                throw new TiendanubeStockPayloadException(
                    'espejo_desactualizado',
                    'No hay niveles sincronizados para esta variante tras activar multi-inventario.'
                );
            }
        }
    }
}
