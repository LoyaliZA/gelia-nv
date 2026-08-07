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
    ];

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
