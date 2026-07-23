<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolicitudTraspasoProducto extends Model
{
    protected $table = 'solicitud_traspaso_productos';

    protected $fillable = [
        'solicitud_traspaso_id',
        'producto_id',
        'sku',
        'descripcion',
        'piezas',
    ];

    protected function casts(): array
    {
        return [
            'piezas' => 'integer',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTraspaso::class, 'solicitud_traspaso_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function detalleDano(): HasOne
    {
        return $this->hasOne(SolicitudTraspasoDetalleDano::class, 'solicitud_traspaso_producto_id');
    }
}
