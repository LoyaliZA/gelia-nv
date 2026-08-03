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
        'width',
        'height',
        'requiere_revision',
        'alerta_pequena',
        'alerta_no_cuadrada',
    ];

    protected function casts(): array
    {
        return [
            'producto_id' => 'integer',
            'position' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'requiere_revision' => 'boolean',
            'alerta_pequena' => 'boolean',
            'alerta_no_cuadrada' => 'boolean',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(TiendanubeProducto::class, 'producto_id');
    }
}
