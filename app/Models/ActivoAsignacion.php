<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivoAsignacion extends Model
{
    protected $table = 'activo_asignaciones';

    protected $fillable = [
        'activo_id',
        'user_id',
        'asignado_por_id',
        'fecha_inicio',
        'fecha_fin',
        'activa',
        'notas',
        'firmado',
        'firma_ruta',
        'firma_fecha',
        'condiciones_entrega',
        'condiciones_devolucion',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'activa' => 'boolean',
        'firmado' => 'boolean',
        'firma_fecha' => 'datetime',
    ];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por_id');
    }
}
