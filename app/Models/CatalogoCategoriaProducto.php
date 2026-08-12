<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoCategoriaProducto extends Model
{
    protected $table = 'catalogo_categoria_productos';

    protected $fillable = [
        'nombre',
        'parent_id',
        'slug',
        'ruta_cache',
        'nivel',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function categoriaAtributos(): HasMany
    {
        return $this->hasMany(CategoriaAtributo::class, 'categoria_id');
    }

    public function categoriaExtensiones(): HasMany
    {
        return $this->hasMany(CategoriaExtension::class, 'categoria_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }
}
