<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubeProductoImagen extends Model
{
    protected $table = 'tiendanube_producto_imagenes';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'producto_id',
        'src',
        'position',
        'alt',
    ];

    protected function casts(): array
    {
        return [
            'producto_id' => 'integer',
            'position' => 'integer',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(TiendanubeProducto::class, 'producto_id');
    }
}
