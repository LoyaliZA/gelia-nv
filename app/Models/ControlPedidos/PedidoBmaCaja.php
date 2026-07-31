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
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'orden' => 'integer',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function tipoCaja(): BelongsTo
    {
        return $this->belongsTo(CatalogoTipoCajaPedido::class, 'catalogo_tipo_caja_id');
    }
}
