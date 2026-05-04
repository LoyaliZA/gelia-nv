<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialMontoCliente extends Model
{
    protected $table = 'historial_montos_clientes';

    protected $fillable = [
        'cliente_id',
        'monto_anterior',
        'monto_nuevo',
        'diferencia_aplicada'
    ];

    protected $casts = [
        'monto_anterior' => 'decimal:2',
        'monto_nuevo' => 'decimal:2',
        'diferencia_aplicada' => 'decimal:2',
    ];

    /**
     * Relación: Este historial pertenece a un cliente específico.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}