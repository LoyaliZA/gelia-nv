<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SoporteCatalogoEstado extends Model
{
    use HasFactory;

    protected $table = 'soporte_catalogo_estados';

    protected $fillable = [
        'nombre',
        'color',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
