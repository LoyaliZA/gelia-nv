<?php

namespace App\Models\SaldosAFavor;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SafMovimiento extends Model
{
    public const TIPO_GENERACION = 'generacion';
    public const TIPO_RESERVA = 'reserva';
    public const TIPO_LIBERACION = 'liberacion';
    public const TIPO_APLICACION = 'aplicacion';
    public const TIPO_AJUSTE_POS = 'ajuste_pos';
    public const TIPO_AJUSTE_NEG = 'ajuste_neg';
    public const TIPO_VENCIMIENTO = 'vencimiento';
    public const TIPO_REACTIVACION = 'reactivacion';
    public const TIPO_CANCELACION = 'cancelacion';
    public const TIPO_REVERSION = 'reversion';

    protected $table = 'saf_movimientos';

    protected $fillable = [
        'saf_credito_id',
        'cliente_id',
        'tipo',
        'monto',
        'saldo_anterior',
        'saldo_posterior',
        'pedido_bma_id',
        'saf_comprobante_caja_id',
        'saf_pedido_aplicacion_id',
        'referencia_externa',
        'usuario_id',
        'saf_motivo_id',
        'observaciones',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_posterior' => 'decimal:2',
    ];

    public function credito(): BelongsTo
    {
        return $this->belongsTo(SafCredito::class, 'saf_credito_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function motivo(): BelongsTo
    {
        return $this->belongsTo(SafMotivo::class, 'saf_motivo_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(SafEvidencia::class, 'saf_movimiento_id');
    }
}
