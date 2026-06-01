<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RhHorasExtra extends Model
{
    use SoftDeletes;

    protected $table = 'rh_horas_extra';

    protected $fillable = [
        'uuid',
        'folio',
        'rh_colaborador_id',
        'fecha_turno',
        'hora_entrada',
        'hora_salida',
        'salida_dia_siguiente',
        'total_horas_laboradas',
        'horas_normales_snapshot',
        'tiempo_extra_crudo',
        'tiempo_extra_minutos',
        'horas_extra_a_pagar',
        'salario_por_hora_snapshot',
        'multiplicador_snapshot',
        'total_economico',
        'motivo',
        'supervisor_user_id',
        'fecha_programada_pago',
        'estado_pago',
        'area_snapshot',
        'registrado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_turno' => 'date',
            'fecha_programada_pago' => 'date',
            'salida_dia_siguiente' => 'boolean',
            'total_horas_laboradas' => 'decimal:2',
            'horas_normales_snapshot' => 'decimal:2',
            'tiempo_extra_crudo' => 'decimal:2',
            'salario_por_hora_snapshot' => 'decimal:4',
            'multiplicador_snapshot' => 'decimal:2',
            'total_economico' => 'decimal:2',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(RhColaborador::class, 'rh_colaborador_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }
}
