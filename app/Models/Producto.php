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
        'tipo_producto_id',
        'sku',
        'descripcion',
        'descripcion_corta',
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

    public function tipoProducto(): BelongsTo
    {
        return $this->belongsTo(TipoProducto::class, 'tipo_producto_id');
    }

    public function atributoValores(): HasMany
    {
        return $this->hasMany(ProductoAtributoValor::class);
    }

    public function relaciones(): HasMany
    {
        return $this->hasMany(ProductoRelacion::class, 'producto_id');
    }

    public function notasOlfativas(): HasMany
    {
        return $this->hasMany(ProductoNotaOlfativa::class);
    }

    public function contenidos(): HasMany
    {
        return $this->hasMany(ProductoContenido::class);
    }

    public function ventasAlmacen(): HasMany
    {
        return $this->hasMany(ProductoVentaAlmacen::class);
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

    /**
     * Palabras útiles para coincidencia parcial (AND en descripción).
     * Compacta "100 ml" → "100ml"; omite partículas cortas.
     *
     * @return list<string>
     */
    public static function tokensBusqueda(string $texto): array
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        if ($texto === '') {
            return [];
        }

        $texto = preg_replace('/(\d+)\s*(ml|mls|g|gr|oz|kg)\b/u', '$1$2', $texto) ?? $texto;
        $texto = preg_replace('/[^\p{L}\p{N}\s\-]/u', ' ', $texto) ?? $texto;
        $raw = preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stop = [
            'de', 'del', 'la', 'el', 'los', 'las', 'un', 'una', 'unos', 'unas',
            'y', 'o', 'a', 'en', 'con', 'por', 'para', 'al', 'vs', 'the', 'of',
        ];

        $out = [];
        foreach ($raw as $t) {
            if (mb_strlen($t) < 2 || in_array($t, $stop, true)) {
                continue;
            }
            $out[$t] = $t;
        }

        return array_values($out);
    }

    public function scopeBuscarPorTexto(Builder $query, string $texto): Builder
    {
        $texto = trim($texto);
        if ($texto === '') {
            return $query;
        }

        $sku = self::normalizarSku($texto);
        $tokens = self::tokensBusqueda($texto);
        $driver = DB::connection()->getDriverName();
        $castFolio = in_array($driver, ['pgsql', 'sqlite'], true)
            ? 'CAST(folio AS TEXT)'
            : 'CAST(folio AS CHAR)';

        return $query->where(function (Builder $q) use ($texto, $sku, $castFolio, $tokens) {
            $q->where('sku', 'like', "%{$sku}%")
                ->orWhere('descripcion', 'like', "%{$texto}%")
                ->orWhere('codigo_barras', 'like', "%{$texto}%")
                ->orWhereRaw("{$castFolio} LIKE ?", ["%{$texto}%"]);

            // Frase no contigua: ARMAF … MANDARIN SKY (palabras AND en descripción).
            if (count($tokens) >= 2) {
                $q->orWhere(function (Builder $and) use ($tokens) {
                    foreach ($tokens as $token) {
                        $and->where('descripcion', 'like', "%{$token}%");
                    }
                });
            }
        });
    }
}
