<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoTipoCajaPedido extends Model
{
    protected $table = 'catalogo_tipos_caja_pedido';

    protected $fillable = ['nombre', 'peso_volumetrico', 'medidas', 'largo', 'ancho', 'alto', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'peso_volumetrico' => 'decimal:4',
        'largo' => 'decimal:2',
        'ancho' => 'decimal:2',
        'alto' => 'decimal:2',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoBma::class, 'catalogo_tipo_caja_id');
    }
}
