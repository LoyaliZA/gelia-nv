<?php

namespace App\Models\ComisionesEmpaque;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorteUnidadComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_corte_unidades';

    protected $fillable = [
        'corte_id', 'unidad_id', 'clave_origen', 'incorporacion_tardia', 'excepcion_resguardo', 'empacado_at',
        'cantidad_comisionable', 'importe_centavos', 'desglose', 'referencia',
    ];

    protected $casts = [
        'incorporacion_tardia' => 'boolean',
        'excepcion_resguardo' => 'boolean',
        'empacado_at' => 'datetime',
        'cantidad_comisionable' => 'integer',
        'importe_centavos' => 'integer',
        'desglose' => 'array',
        'referencia' => 'array',
    ];

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadComisionEmpaque::class, 'unidad_id');
    }
}
