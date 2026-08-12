<?php

namespace App\Models\SaldosAFavor;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafPedidoAplicacion extends Model
{
    public const ESTADO_RESERVADO = 'reservado';
    public const ESTADO_APLICADO = 'aplicado';
    public const ESTADO_LIBERADO = 'liberado';

    protected $table = 'saf_pedido_aplicaciones';

    protected $fillable = [
        'pedido_bma_id',
        'saf_credito_id',
        'monto',
        'estado',
        'reservado_por_id',
        'aplicado_por_id',
        'reservado_at',
        'aplicado_at',
        'liberado_at',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'reservado_at' => 'datetime',
        'aplicado_at' => 'datetime',
        'liberado_at' => 'datetime',
    ];

    public function credito(): BelongsTo
    {
        return $this->belongsTo(SafCredito::class, 'saf_credito_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function reservadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reservado_por_id');
    }

    public function aplicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aplicado_por_id');
    }
}
