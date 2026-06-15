<?php

namespace App\Models\Woocommerce;

use Illuminate\Database\Eloquent\Model;

class WoocommerceProduct extends Model
{
    protected $table = 'woocommerce_products';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'sku',
        'nombre',
        'precio_normal',
        'precio_rebajado',
        'tipo',
        'parent_id',
    ];

    protected $casts = [
        'precio_normal' => 'float',
        'precio_rebajado' => 'float',
        'parent_id' => 'integer',
    ];
}
