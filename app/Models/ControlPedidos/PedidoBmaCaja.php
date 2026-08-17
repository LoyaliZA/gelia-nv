<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoBmaCaja extends Model
{
    protected $table = 'pedido_bma_cajas';

    protected $fillable = [
        'pedido_bma_id',
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
    ];

    public const ESTATUS_PENDIENTE = 'pendiente';
    public const ESTATUS_RECOLECTADA = 'recolectada';

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
    ];

    public function estaPendiente(): bool
    {
        return ($this->estatus_recoleccion ?: self::ESTATUS_PENDIENTE) === self::ESTATUS_PENDIENTE;
    }

    public function estaRecolectada(): bool
    {
        return $this->estatus_recoleccion === self::ESTATUS_RECOLECTADA;
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
}
