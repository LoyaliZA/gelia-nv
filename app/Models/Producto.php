<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Producto extends Model
{
    protected $fillable = [
        'uuid',
        'folio',
        'categoria_id',
        'marca_id',
        'sku',
        'descripcion',
        'codigo_barras',
        'peso',
        'imagen_path',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'folio' => 'integer',
            'peso' => 'decimal:3',
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Producto $producto) {
            if (empty($producto->uuid)) {
                $producto->uuid = (string) Str::uuid();
            }
        });
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CatalogoCategoriaProducto::class, 'categoria_id');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(CatalogoMarcaProducto::class, 'marca_id');
    }

    public function inventarios(): HasMany
    {
        return $this->hasMany(Inventario::class);
    }

    public function costos(): HasMany
    {
        return $this->hasMany(ProductoCosto::class);
    }

    public function getCostoAttribute(): float
    {
        $costo = $this->relationLoaded('costos')
            ? $this->costos->first()
            : $this->costos()->first();

        return (float) ($costo?->costo ?? 0);
    }

    public function getPrecioVentaAttribute(): ?float
    {
        $costo = $this->relationLoaded('costos')
            ? $this->costos->first()
            : $this->costos()->first();

        return $costo?->precio_venta !== null ? (float) $costo->precio_venta : null;
    }

    public static function normalizarSku(string $sku): string
    {
        return ltrim(trim($sku), '0') ?: '0';
    }

    public function scopeBuscarPorTexto(Builder $query, string $texto): Builder
    {
        $texto = trim($texto);
        if ($texto === '') {
            return $query;
        }

        $sku = self::normalizarSku($texto);
        $driver = DB::connection()->getDriverName();
        $castFolio = in_array($driver, ['pgsql', 'sqlite'], true)
            ? 'CAST(folio AS TEXT)'
            : 'CAST(folio AS CHAR)';

        return $query->where(function (Builder $q) use ($texto, $sku, $castFolio) {
            $q->where('sku', 'like', "%{$sku}%")
                ->orWhere('descripcion', 'like', "%{$texto}%")
                ->orWhere('codigo_barras', 'like', "%{$texto}%")
                ->orWhereRaw("{$castFolio} LIKE ?", ["%{$texto}%"]);
        });
    }
}
