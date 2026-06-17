<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SoporteConfiguracion extends Model
{
    use HasFactory;

    protected $table = 'soporte_configuraciones';

    protected $fillable = [
        'horario_inicio',
        'horario_fin',
        'mensaje_fuera_horario',
        'hora_notificacion_diaria',
        'modo_pruebas',
    ];

    protected $casts = [
        'horario_inicio' => 'datetime:H:i',
        'horario_fin' => 'datetime:H:i',
        'hora_notificacion_diaria' => 'datetime:H:i',
        'modo_pruebas' => 'boolean',
    ];
}
