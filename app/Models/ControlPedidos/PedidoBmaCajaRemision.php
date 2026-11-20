<?php

namespace App\Models\ControlPedidos;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoBmaCajaRemision extends Model
{
    protected $table = 'pedido_bma_caja_remisiones';

    protected $fillable = [
        'pedido_bma_caja_id',
        'pedido_bma_id',
        'solicitud_id',
        'folio_externo',
        'cliente_id',
        'contenido',
        'evidencia_documento_id',
        'valor_asegurado',
        'mercancia_nueva_por_cobrar',
        'cabe',
        'activa',
        'exclusividad_activa',
        'sustituida_at',
    ];

    protected $casts = [
        'valor_asegurado' => 'decimal:2',
        'mercancia_nueva_por_cobrar' => 'decimal:2',
        'cabe' => 'boolean',
        'activa' => 'boolean',
        'sustituida_at' => 'datetime',
    ];

    public function caja(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaCaja::class, 'pedido_bma_caja_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
