<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoCategoriaActivo extends Model
{
    protected $table = 'catalogo_categorias_activo';

    protected $fillable = [
        'nombre',
        'slug',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function activos(): HasMany
    {
        return $this->hasMany(Activo::class, 'catalogo_categoria_activo_id');
    }
}
