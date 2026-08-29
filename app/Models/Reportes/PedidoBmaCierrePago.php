<?php

namespace App\Models\Reportes;

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Departamento;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoBmaCierrePago extends Model
{
    public const ESTADO_VIGENTE = 'vigente';

    public const ESTADO_REVOCADO = 'revocado';

    public const ORIGEN_FLUJO = 'flujo';

    public const ORIGEN_BACKFILL = 'backfill';

    protected $table = 'pedido_bma_cierres_pago';

    protected $fillable = [
        'pedido_bma_id',
        'version',
        'estado',
        'origen',
        'pedido_fecha',
        'validado_at',
        'validado_por_id',
        'revocado_at',
        'revocado_por_id',
        'motivo_revocacion',
        'monto_venta',
        'monto_envio',
        'monto_seguro',
        'total_pedido',
        'saf_aplicado',
        'total_a_cobrar',
        'pagos_validos',
        'diferencia',
        'excedente',
        'tolerancia_aplicada',
        'estado_cobertura',
        'folio_snapshot',
        'folio_remision_snapshot',
        'cliente_id',
        'vendedor_id',
        'departamento_id',
        'almacen_id',
        'metadata_snapshot',
    ];

    protected $casts = [
        'version' => 'integer',
        'pedido_fecha' => 'date',
        'validado_at' => 'datetime',
        'revocado_at' => 'datetime',
        'monto_venta' => 'decimal:2',
        'monto_envio' => 'decimal:2',
        'monto_seguro' => 'decimal:2',
        'total_pedido' => 'decimal:2',
        'saf_aplicado' => 'decimal:2',
        'total_a_cobrar' => 'decimal:2',
        'pagos_validos' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'excedente' => 'decimal:2',
        'tolerancia_aplicada' => 'decimal:2',
        'metadata_snapshot' => 'array',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoBmaCierrePagoItem::class, 'pedido_bma_cierre_pago_id')
            ->orderBy('numero_exhibicion');
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por_id');
    }

    public function revocadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revocado_por_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function scopeVigente(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_VIGENTE);
    }

    public function scopeRevocado(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_REVOCADO);
    }
}
