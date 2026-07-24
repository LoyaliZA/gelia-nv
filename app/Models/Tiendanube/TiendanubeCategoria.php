<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TiendanubeCategoria extends Model
{
    protected $table = 'tiendanube_categorias';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'name',
        'handle',
        'description',
        'parent_id',
        'seo_title',
        'seo_description',
        'gelia_categoria_id',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'handle' => 'array',
            'description' => 'array',
            'parent_id' => 'integer',
            'gelia_categoria_id' => 'integer',
        ];
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(
            TiendanubeProducto::class,
            'tiendanube_producto_categoria',
            'categoria_id',
            'producto_id'
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
}
