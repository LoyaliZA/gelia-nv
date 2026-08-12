<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoVentaAlmacen extends Model
{
    protected $table = 'producto_ventas_almacen';

    protected $fillable = [
        'producto_id',
        'almacen_id',
        'periodo',
        'cantidad_vendida',
        'monto_venta',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_vendida' => 'decimal:3',
            'monto_venta' => 'decimal:2',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }
}
