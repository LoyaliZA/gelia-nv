<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoTipoActivo extends Model
{
    protected $table = 'catalogo_tipos_activo';

    protected $fillable = [
        'nombre',
        'slug',
        'categoria',
        'icono',
        'esquema_atributos',
        'activo',
    ];

    protected $casts = [
        'esquema_atributos' => 'array',
        'activo' => 'boolean',
    ];

    public function activos(): HasMany
    {
        return $this->hasMany(Activo::class, 'catalogo_tipo_activo_id');
    }
}
