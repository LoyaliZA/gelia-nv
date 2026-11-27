<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpresionGuiaCedis extends Model
{
    protected $table = 'impresiones_guias_cedis';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'comunicada_at' => 'datetime',
        'impresion_confirmada_at' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaDocumento::class, 'documento_id');
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaCaja::class, 'pedido_bma_caja_id');
    }
}
