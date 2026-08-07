<?php

namespace App\Models\SaldosAFavor;

use App\Models\Cliente;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SafCredito extends Model
{
    public const ESTADO_DISPONIBLE = 'disponible';
    public const ESTADO_PARCIAL = 'parcialmente_aplicado';
    public const ESTADO_APLICADO = 'aplicado';
    public const ESTADO_VENCIDO = 'vencido';
    public const ESTADO_CANCELADO = 'cancelado';

    public const REVISION_PENDIENTE = 'pendiente';
    public const REVISION_REVISADO = 'revisado';
    public const REVISION_CON_DIFERENCIA = 'con_diferencia';
    public const REVISION_REQUIERE_CORRECCION = 'requiere_correccion';
    public const REVISION_AJUSTADO = 'ajustado';
    public const REVISION_RECHAZADO = 'rechazado';

    public const VIGENCIA_DIAS = 365;

    protected $table = 'saf_creditos';

    protected $fillable = [
        'folio',
        'saf_cuenta_id',
        'cliente_id',
        'canal_origen',
        'sucursal',
        'departamento',
        'pedido_bma_id',
        'documento_origen',
        'monto_original',
        'monto_aplicado',
        'monto_reservado',
        'monto_disponible',
        'fecha_generacion',
        'fecha_vencimiento',
        'saf_motivo_id',
        'detalle_motivo',
        'generado_por_id',
        'estado_financiero',
        'estado_revision',
        'observaciones_revision',
        'revisado_por_id',
        'revisado_at',
    ];

    protected $casts = [
        'monto_original' => 'decimal:2',
        'monto_aplicado' => 'decimal:2',
        'monto_reservado' => 'decimal:2',
        'monto_disponible' => 'decimal:2',
        'fecha_generacion' => 'datetime',
        'fecha_vencimiento' => 'date',
        'revisado_at' => 'datetime',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(SafCuenta::class, 'saf_cuenta_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function motivo(): BelongsTo
    {
        return $this->belongsTo(SafMotivo::class, 'saf_motivo_id');
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_id');
    }

    public function pedidoOrigen(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(SafMovimiento::class, 'saf_credito_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(SafEvidencia::class, 'saf_credito_id');
    }

    public function aplicacionesPedido(): HasMany
    {
        return $this->hasMany(SafPedidoAplicacion::class, 'saf_credito_id');
    }

    public function puedeUsarse(): bool
    {
        return in_array($this->estado_financiero, [self::ESTADO_DISPONIBLE, self::ESTADO_PARCIAL], true)
            && (float) $this->monto_disponible > 0
            && $this->estado_financiero !== self::ESTADO_CANCELADO
            && $this->estado_financiero !== self::ESTADO_VENCIDO;
    }

    public function recalcularEstadoFinanciero(): void
    {
        if ($this->estado_financiero === self::ESTADO_CANCELADO) {
            return;
        }
        if ($this->estado_financiero === self::ESTADO_VENCIDO && (float) $this->monto_disponible <= 0) {
            $this->estado_financiero = self::ESTADO_APLICADO;

            return;
        }
        if ($this->estado_financiero === self::ESTADO_VENCIDO) {
            return;
        }

        $disponible = (float) $this->monto_disponible;
        $aplicado = (float) $this->monto_aplicado;

        if ($disponible <= 0 && $aplicado > 0) {
            $this->estado_financiero = self::ESTADO_APLICADO;
        } elseif ($aplicado > 0) {
            $this->estado_financiero = self::ESTADO_PARCIAL;
        } else {
            $this->estado_financiero = self::ESTADO_DISPONIBLE;
        }
    }

    public function sincronizarDisponible(): void
    {
        $this->monto_disponible = round(
            (float) $this->monto_original - (float) $this->monto_aplicado - (float) $this->monto_reservado,
            2
        );
        $this->recalcularEstadoFinanciero();
    }
}
