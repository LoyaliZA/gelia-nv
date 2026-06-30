<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoCosto extends Model
{
    protected $table = 'producto_costos';

    protected $fillable = [
        'producto_id',
        'almacen_id',
        'costo',
        'costo_reposicion',
        'precio_venta',
    ];

    protected function casts(): array
    {
        return [
            'costo' => 'decimal:2',
            'costo_reposicion' => 'decimal:2',
            'precio_venta' => 'decimal:2',
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
