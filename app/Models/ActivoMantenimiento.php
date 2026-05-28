<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivoMantenimiento extends Model
{
    protected $table = 'activo_mantenimientos';

    protected $fillable = [
        'activo_id',
        'usuario_id',
        'tipo',
        'estado',
        'fecha_programada',
        'fecha_inicio',
        'fecha_fin',
        'proveedor',
        'costo',
        'descripcion',
        'notas',
        'proximo_mantenimiento',
    ];

    protected $casts = [
        'fecha_programada' => 'date',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'proximo_mantenimiento' => 'date',
        'costo' => 'decimal:2',
    ];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
