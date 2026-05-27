<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalizacionTono extends Model
{
    protected $table = 'personalizacion_tonos';

    protected $fillable = [
        'slug',
        'nombre',
        'archivo',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden'  => 'integer',
    ];
}
