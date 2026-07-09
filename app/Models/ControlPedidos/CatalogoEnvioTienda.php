<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoEnvioTienda extends Model
{
    protected $table = 'catalogo_envios_tienda';

    protected $fillable = ['nombre', 'es_otro', 'activo'];

    protected $casts = [
        'es_otro' => 'boolean',
        'activo' => 'boolean',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoBma::class, 'catalogo_envio_tienda_id');
    }
}
