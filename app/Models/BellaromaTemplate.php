<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BellaromaTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre_archivo',
        'ruta_fisica',
        'tamano_kb',
        'enviado_correo',
    ];
}
