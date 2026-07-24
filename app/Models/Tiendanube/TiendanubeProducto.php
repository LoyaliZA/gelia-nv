<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function skuPrincipal(): ?string
    {
        $variante = $this->relationLoaded('variantes')
            ? $this->variantes->first()
            : $this->variantes()->first();

        return $variante?->sku;
    }
}
