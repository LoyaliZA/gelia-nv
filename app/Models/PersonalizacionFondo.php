<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalizacionFondo extends Model
{
    protected $table = 'personalizacion_fondos';

    protected $fillable = [
        'slug',
        'nombre',
        'tipo',
        'valor',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden'  => 'integer',
    ];
}
