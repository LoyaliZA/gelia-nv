<?php

namespace App\Models\ComisionesEmpaque;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodoComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_periodos';

    protected $fillable = ['area_id', 'nombre', 'desde', 'hasta', 'motivo', 'reparto_origen_id', 'creado_por_id'];

    protected $casts = ['desde' => 'datetime', 'hasta' => 'datetime'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(AreaComisionEmpaque::class, 'area_id');
    }

    public function corte(): ?CorteComisionEmpaque
    {
        return CorteComisionEmpaque::query()->where('area_id', $this->area_id)
            ->where('estado', CorteComisionEmpaque::CONFIRMADO)
            ->where('limite_desde', '<', $this->hasta)->where('limite_hasta', '>=', $this->desde)
            ->with('asignaciones.participante')->first();
    }
}
