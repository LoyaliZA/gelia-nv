<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiendanubeProductoVariante extends Model
{
    protected $table = 'tiendanube_producto_variantes';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'producto_id',
        'sku',
        'price',
        'promotional_price',
        'cost',
        'stock',
        'stock_management',
        'values',
        'barcode',
        'weight',
    ];

    protected function casts(): array
    {
        return [
            'producto_id' => 'integer',
            'price' => 'float',
            'promotional_price' => 'float',
            'cost' => 'float',
            'stock' => 'integer',
            'stock_management' => 'boolean',
            'values' => 'array',
            'weight' => 'float',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(TiendanubeProducto::class, 'producto_id');
    }

    public function nivelesInventario(): HasMany
    {
        return $this->hasMany(TiendanubeVarianteNivel::class, 'variante_id');
    }

    public function stockTotal(): ?int
    {
        $niveles = $this->relationLoaded('nivelesInventario')
            ? $this->nivelesInventario
            : $this->nivelesInventario()->get();

        if ($niveles->isEmpty()) {
            return null;
        }

        if ($niveles->contains(fn (TiendanubeVarianteNivel $n) => $n->stock === null)) {
            return null;
        }

        return (int) $niveles->sum('stock');
    }

    /**
     * @return array{total: ?int, ilimitado: bool, origen: string, stock_legado: ?int, niveles: list<array{location_id: string, nombre: string, stock: ?int}>}
     */
    public function stockResumen(): array
    {
        $niveles = $this->relationLoaded('nivelesInventario')
            ? $this->nivelesInventario
            : $this->nivelesInventario()->with('ubicacion')->get();

        $filas = [];
        foreach ($niveles as $nivel) {
            $filas[] = [
                'location_id' => (string) $nivel->ubicacion_id,
                'nombre' => $nivel->ubicacion?->nombreVisible() ?? (string) $nivel->ubicacion_id,
                'stock' => $nivel->stock,
            ];
        }

        $sinControl = ! $this->stock_management;
        if ($filas === []) {
            $ilimitado = $sinControl || $this->stock === null;

            return [
                'total' => $ilimitado ? null : $this->stock,
                'ilimitado' => $ilimitado,
                'origen' => $ilimitado ? 'ilimitado' : 'legado',
                'stock_legado' => $this->stock,
                'niveles' => [],
            ];
        }

        $algunIlimitado = collect($filas)->contains(fn (array $f) => $f['stock'] === null);
        $total = $algunIlimitado ? null : (int) collect($filas)->sum('stock');

        return [
            'total' => $total,
            'ilimitado' => $sinControl || $algunIlimitado,
            'origen' => 'niveles',
            'stock_legado' => $this->stock,
            'niveles' => $filas,
        ];
    }
}
