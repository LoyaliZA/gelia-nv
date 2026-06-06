<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CobranzaFactura extends Model
{
    protected $table = 'cobranza_facturas';

    protected $fillable = [
        'cliente_id',
        'folio',
        'monto',
        'fecha_emision',
        'fecha_vencimiento',
        'pagada',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'pagada' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(CobranzaAlerta::class, 'factura_id');
    }
}
