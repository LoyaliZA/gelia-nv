<?php

namespace App\Models\Woocommerce;

use Illuminate\Database\Eloquent\Model;

class WoocommerceMargin extends Model
{
    protected $table = 'woocommerce_margins';

    protected $fillable = [
        'precio_min',
        'precio_max',
        'multiplicador_rebaja',
        'multiplicador_normal',
    ];

    protected $casts = [
        'precio_min' => 'float',
        'precio_max' => 'float',
        'multiplicador_rebaja' => 'float',
        'multiplicador_normal' => 'float',
    ];
}
