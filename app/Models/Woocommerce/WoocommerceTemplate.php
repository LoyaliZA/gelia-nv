<?php

namespace App\Models\Woocommerce;

use Illuminate\Database\Eloquent\Model;

class WoocommerceTemplate extends Model
{
    protected $table = 'woocommerce_templates';

    protected $fillable = [
        'nombre_archivo',
        'ruta_fisica',
        'tamano_kb',
        'subido_drive',
        'drive_id',
    ];

    protected $casts = [
        'subido_drive' => 'boolean',
    ];
}
