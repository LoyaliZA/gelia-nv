<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobranzaAlerta extends Model
{
    protected $table = 'cobranza_alertas';

    protected $fillable = [
        'cliente_id',
        'factura_id',
        'dias_atraso',
        'fecha_alerta',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'dias_atraso' => 'integer',
        'fecha_alerta' => 'date',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(CobranzaFactura::class, 'factura_id');
    }
}
