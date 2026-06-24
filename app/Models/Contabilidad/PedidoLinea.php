<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoLinea extends Model
{
    protected $table = 'contabilidad_pedido_lineas';

    protected $fillable = [
        'pedido_id',
        'sku',
        'piezas',
        'nombre_producto',
        'precio_unitario',
        'subtotal',
        'tipo_devolucion',
    ];

    protected $casts = [
        'piezas' => 'integer',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }
}
