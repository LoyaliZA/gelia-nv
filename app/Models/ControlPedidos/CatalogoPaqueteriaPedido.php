<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoPaqueteriaPedido extends Model
{
    protected $table = 'catalogo_paqueterias_pedido';

    public const CATEGORIA_COMERCIAL = 'comercial';
    public const CATEGORIA_LOCAL_REGIONAL = 'local_regional';

    protected $fillable = ['nombre', 'categoria', 'activo', 'costo_seguro_default'];

    protected $casts = [
        'activo' => 'boolean',
        'costo_seguro_default' => 'decimal:2',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoBma::class, 'catalogo_paqueteria_id');
    }

    public function ofreceRastreo(): bool
    {
        return $this->categoria === self::CATEGORIA_COMERCIAL;
    }
}
