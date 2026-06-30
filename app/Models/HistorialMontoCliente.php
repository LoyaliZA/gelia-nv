<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialMontoCliente extends Model
{
    protected $table = 'historial_montos_clientes';

    protected $fillable = [
        'cliente_id',
        'usuario_id',
        'monto_anterior',
        'monto_nuevo',
        'diferencia_aplicada',
        'origen',
        'importacion_cliente_id',
        'solicitud_id',
        'monto_operacion',
        'notas',
    ];

    protected $casts = [
        'monto_anterior' => 'decimal:2',
        'monto_nuevo' => 'decimal:2',
        'diferencia_aplicada' => 'decimal:2',
        'monto_operacion' => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(ImportacionCliente::class, 'importacion_cliente_id');
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTag::class, 'solicitud_id');
    }
}