<?php

namespace App\Models\ControlPedidos;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoBmaCaja extends Model
{
    protected $table = 'pedido_bma_cajas';

    public const ESTATUS_PENDIENTE = 'pendiente';

    public const ESTATUS_RECOLECTADA = 'recolectada';

    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_RETIRADA = 'retirada';

    protected $fillable = [
        'pedido_bma_id',
        'uuid_operativo',
        'catalogo_tipo_caja_id',
        'cantidad',
        'orden',
        'largo',
        'ancho',
        'alto',
        'peso_real_kg',
        'peso_volumetrico_kg',
        'peso_cobrado_kg',
        'catalogo_tipo_guia_id',
        'estatus_recoleccion',
        'recolectada_at',
        'recolectada_por_id',
        'numero_rastreo',
        'costo_envio',
        'costo_seguro',
        'costo_adicional',
        'concepto_adicional',
        'moneda',
        'estado_operativo',
        'retirada_at',
        'retirada_por_id',
        'motivo_retiro',
        'costos_actualizados_at',
        'costos_actualizados_por_id',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'orden' => 'integer',
        'largo' => 'float',
        'ancho' => 'float',
        'alto' => 'float',
        'peso_real_kg' => 'float',
        'peso_volumetrico_kg' => 'float',
        'peso_cobrado_kg' => 'float',
        'recolectada_at' => 'datetime',
        'costo_envio' => 'decimal:2',
        'costo_seguro' => 'decimal:2',
        'costo_adicional' => 'decimal:2',
        'retirada_at' => 'datetime',
        'costos_actualizados_at' => 'datetime',
    ];

    public function estaPendiente(): bool
    {
        return ($this->estatus_recoleccion ?: self::ESTATUS_PENDIENTE) === self::ESTATUS_PENDIENTE;
    }

    public function estaRecolectada(): bool
    {
        return $this->estatus_recoleccion === self::ESTATUS_RECOLECTADA;
    }

    public function estaRetirada(): bool
    {
        return $this->estado_operativo === self::ESTADO_RETIRADA;
    }

    public function estaActiva(): bool
    {
        return ! $this->estaRetirada();
    }

    public function tieneDesgloseCosto(): bool
    {
        return $this->costo_envio !== null && $this->costo_envio !== '';
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('estado_operativo', self::ESTADO_ACTIVA)
                ->orWhereNull('estado_operativo');
        });
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function tipoCaja(): BelongsTo
    {
        return $this->belongsTo(CatalogoTipoCajaPedido::class, 'catalogo_tipo_caja_id');
    }

    public function tipoGuia(): BelongsTo
    {
        return $this->belongsTo(CatalogoTipoGuiaPedido::class, 'catalogo_tipo_guia_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(PedidoBmaDocumento::class, 'pedido_bma_caja_id');
    }

    public function retiradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retirada_por_id');
    }
}
