<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoAlmacenSalida extends Model
{
    protected $table = 'catalogo_almacenes_salida';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoBma::class, 'catalogo_almacen_salida_id');
    }
}
