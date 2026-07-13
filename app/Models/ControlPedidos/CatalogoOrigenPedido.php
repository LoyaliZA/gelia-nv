<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoOrigenPedido extends Model
{
    protected $table = 'origenes_pedido';

    protected $fillable = ['nombre', 'requiere_logistica', 'activo'];

    protected $casts = [
        'requiere_logistica' => 'boolean',
        'activo' => 'boolean',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoBma::class, 'origen_id');
    }
}
