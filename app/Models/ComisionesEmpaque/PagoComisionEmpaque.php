<?php

namespace App\Models\ComisionesEmpaque;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PagoComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_pagos';

    protected $fillable = [
        'participante_id', 'fecha_pago', 'importe_centavos', 'referencia', 'medio', 'observaciones',
        'clave_idempotencia', 'estado', 'motivo_reversion', 'registrado_por_id', 'revertido_por_id', 'revertido_at',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'importe_centavos' => 'integer',
        'revertido_at' => 'datetime',
    ];

    public function participante(): BelongsTo
    {
        return $this->belongsTo(ParticipanteComisionEmpaque::class, 'participante_id');
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(PagoAplicacionComisionEmpaque::class, 'pago_id');
    }
}
