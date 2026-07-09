<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoZonaPedido extends Model
{
    protected $table = 'catalogo_zonas_pedido';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoBma::class, 'catalogo_zona_id');
    }
}
