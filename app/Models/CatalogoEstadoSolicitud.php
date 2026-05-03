<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoEstadoSolicitud extends Model
{
    protected $table = 'catalogo_estados_solicitud';

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
        return $this->hasMany(SolicitudTag::class, 'catalogo_estado_solicitud_id');
    }
}