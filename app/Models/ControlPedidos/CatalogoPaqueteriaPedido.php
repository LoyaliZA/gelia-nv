<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoPaqueteriaPedido extends Model
{
    protected $table = 'catalogo_paqueterias_pedido';

    protected $fillable = ['nombre', 'activo', 'costo_seguro_default'];

    protected $casts = [
        'activo' => 'boolean',
        'costo_seguro_default' => 'decimal:2',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoBma::class, 'catalogo_paqueteria_id');
    }
}
