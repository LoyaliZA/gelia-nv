<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaAcceso extends Model
{
    protected $table = 'auditorias_accesos';

    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'dispositivo',
        'plataforma',
        'navegador',
        'ubicacion_ciudad',
        'ubicacion_region',
        'ubicacion_pais',
        'inicio_sesion_at',
        'ultima_actividad_at',
        'cierre_sesion_at',
        'motivo_cierre',
        'duracion_activa_segundos',
        'duracion_inactiva_segundos',
    ];

    protected $casts = [
        'inicio_sesion_at' => 'datetime',
        'ultima_actividad_at' => 'datetime',
        'cierre_sesion_at' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estaActiva(): bool
    {
        return $this->cierre_sesion_at === null;
    }
}
