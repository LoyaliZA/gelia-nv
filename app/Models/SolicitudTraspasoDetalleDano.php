<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudTraspasoDetalleDano extends Model
{
    protected $table = 'solicitud_traspaso_detalle_danos';

    protected $fillable = [
        'solicitud_traspaso_id',
        'solicitud_traspaso_producto_id',
        'motivo',
        'paths',
        'reportado_por_id',
        'reportado_at',
    ];

    protected function casts(): array
    {
        return [
            'paths' => 'array',
            'reportado_at' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTraspaso::class, 'solicitud_traspaso_id');
    }

    public function productoLinea(): BelongsTo
    {
        return $this->belongsTo(SolicitudTraspasoProducto::class, 'solicitud_traspaso_producto_id');
    }

    public function reportadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reportado_por_id');
    }
}
