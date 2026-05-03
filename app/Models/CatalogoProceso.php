<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoProceso extends Model
{
    protected $table = 'catalogo_procesos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudTag::class, 'catalogo_proceso_id');
    }

    public function tabuladores(): HasMany
    {
        return $this->hasMany(TabuladorComision::class, 'catalogo_proceso_id');
    }
}