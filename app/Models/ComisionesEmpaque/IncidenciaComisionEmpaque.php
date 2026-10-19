<?php

namespace App\Models\ComisionesEmpaque;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidenciaComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_incidencias';

    protected $fillable = [
        'unidad_id', 'tipo', 'estado', 'decision', 'motivo', 'responsable_id', 'resuelta_at', 'datos',
    ];

    protected $casts = ['resuelta_at' => 'datetime', 'datos' => 'array'];

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadComisionEmpaque::class, 'unidad_id');
    }
}
