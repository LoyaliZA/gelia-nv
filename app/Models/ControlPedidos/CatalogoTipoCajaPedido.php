<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoTipoCajaPedido extends Model
{
    protected $table = 'catalogo_tipos_caja_pedido';

    protected $fillable = ['nombre', 'peso_volumetrico', 'medidas', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'peso_volumetrico' => 'decimal:4',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoBma::class, 'catalogo_tipo_caja_id');
    }
}
