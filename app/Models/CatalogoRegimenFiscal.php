<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CatalogoRegimenFiscal extends Model
{
    protected $table = 'catalogo_regimen_fiscal';

    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
