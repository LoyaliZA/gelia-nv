<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoAlmacen extends Model
{
    protected $table = 'producto_almacen';

    protected $fillable = [
        'producto_id',
        'almacen_id',
        'ubicacion',
        'activo_en_almacen',
        'origen_asignacion',
    ];

    protected function casts(): array
    {
        return [
            'activo_en_almacen' => 'boolean',
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
