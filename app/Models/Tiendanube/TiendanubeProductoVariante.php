<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubeProductoVariante extends Model
{
    protected $table = 'tiendanube_producto_variantes';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'producto_id',
        'sku',
        'price',
        'promotional_price',
        'cost',
        'stock',
        'stock_management',
        'values',
        'barcode',
        'weight',
    ];

    protected function casts(): array
    {
        return [
            'producto_id' => 'integer',
            'price' => 'float',
            'promotional_price' => 'float',
            'cost' => 'float',
            'stock' => 'integer',
            'stock_management' => 'boolean',
            'values' => 'array',
            'weight' => 'float',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(TiendanubeProducto::class, 'producto_id');
    }
}
