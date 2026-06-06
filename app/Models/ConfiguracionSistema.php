<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionSistema extends Model
{
    use HasFactory;

    protected $table = 'configuraciones_sistema';

    protected $fillable = [
        'clave',
        'valor',
        'tipo',
        'descripcion',
        'grupo',
    ];
}
