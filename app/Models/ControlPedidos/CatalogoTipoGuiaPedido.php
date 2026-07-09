<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoTipoGuiaPedido extends Model
{
    protected $table = 'catalogo_tipos_guia_pedido';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoBma::class, 'catalogo_tipo_guia_id');
    }
}
