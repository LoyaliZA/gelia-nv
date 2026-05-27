<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalizacionTema extends Model
{
    protected $table = 'personalizacion_temas';

    protected $fillable = [
        'slug',
        'nombre',
        'configuracion',
        'activo',
        'orden',
    ];

    protected $casts = [
        'configuracion' => 'array',
        'activo'        => 'boolean',
        'orden'         => 'integer',
    ];
}
