<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoBmaEnvioResguardo extends Model
{
    protected $table = 'pedido_bma_envios_resguardo';

    protected $fillable = [
        'pedido_envio_id',
        'pedido_resguardo_id',
    ];

    public function envio(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_envio_id');
    }

    public function resguardo(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_resguardo_id');
    }
}
