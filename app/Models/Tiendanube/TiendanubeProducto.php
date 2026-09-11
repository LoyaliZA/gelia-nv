<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class TiendanubeProducto extends Model
{
    protected $table = 'tiendanube_productos';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'name',
        'description',
        'handle',
        'brand',
        'published',
        'free_shipping',
        'requires_shipping',
        'video_url',
        'seo_title',
        'seo_description',
        'tags',
        'attributes',
        'canonical_url',
        'synced_at',
        'gelia_producto_id',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'handle' => 'array',
            'attributes' => 'array',
            'published' => 'boolean',
            'free_shipping' => 'boolean',
            'requires_shipping' => 'boolean',
            'synced_at' => 'datetime',
            'gelia_producto_id' => 'integer',
        ];
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(TiendanubeProductoImagen::class, 'producto_id')->orderBy('position');
    }

    public function variantes(): HasMany
    {
        return $this->hasMany(TiendanubeProductoVariante::class, 'producto_id');
    }

    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(
            TiendanubeCategoria::class,
            'tiendanube_producto_categoria',
            'producto_id',
            'categoria_id'
        );
    }

    public function nombreVisible(): string
    {
        $name = $this->name;
        if (! is_array($name)) {
            return (string) ($name ?? '');
        }

        return (string) ($name['es'] ?? $name['es_MX'] ?? reset($name) ?: '');
    }

    /**
     * Búsqueda de catálogo: ID, SEO, marca, SKU y nombre JSON localizado.
     * Acentos/mayúsculas dependen de la collation del motor (MySQL unicode_ci vs sqlite).
     */
    public function scopeBuscarTextoCatalogo(Builder $query, string $texto, bool $incluirTags = true): Builder
    {
        $texto = trim($texto);
        if ($texto === '') {
            return $query;
        }

        $like = '%'.$texto.'%';
        $driver = DB::connection()->getDriverName();
        $langs = ['es', 'es_MX', 'es_AR', 'pt', 'en'];

        return $query->where(function (Builder $inner) use ($texto, $like, $incluirTags, $driver, $langs) {
            $inner->where('id', $texto)
                ->orWhere('seo_title', 'LIKE', $like)
                ->orWhere('brand', 'LIKE', $like);

            if ($incluirTags) {
                $inner->orWhere('tags', 'LIKE', $like);
            }

            foreach ($langs as $lang) {
                $extract = $driver === 'mysql'
                    ? "JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$lang}'))"
                    : "json_extract(name, '$.{$lang}')";
                $inner->orWhereRaw("{$extract} LIKE ?", [$like]);
            }

            $inner->orWhereHas('variantes', fn ($v) => $v->where('sku', 'LIKE', $like));
        });
    }

    public function skuPrincipal(): ?string
    {
        $variante = $this->relationLoaded('variantes')
            ? $this->variantes->first()
            : $this->variantes()->first();

        return $variante?->sku;
    }
}
