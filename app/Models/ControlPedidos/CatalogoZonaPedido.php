<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoZonaPedido extends Model
{
    protected $table = 'catalogo_zonas_pedido';

    protected $fillable = ['nombre', 'costo_adicional', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'costo_adicional' => 'decimal:2',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoBma::class, 'catalogo_zona_id');
    }

    /** Monto de reexpedición configurado en la zona (0 si no aplica). */
    public function costoReexpedicion(): float
    {
        return max(0.0, (float) ($this->costo_adicional ?? 0));
    }
}
