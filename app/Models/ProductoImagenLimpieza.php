<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoImagenLimpieza extends Model
{
    protected $table = 'producto_imagen_limpiezas';

    protected $fillable = [
        'clave',
        'intentos',
    ];
}
