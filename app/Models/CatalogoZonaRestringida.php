<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogoZonaRestringida extends Model
{
    protected $table = 'catalogo_zonas_restringidas';

    protected $fillable = [
        'nombre',
        'coordenadas_poligono',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'coordenadas_poligono' => 'array',
            'activo' => 'boolean',
        ];
    }
}