<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SoporteCatalogoModulo extends Model
{
    use HasFactory;

    protected $table = 'soporte_catalogo_modulos';

    protected $fillable = [
        'nombre',
        'permiso_requerido',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
