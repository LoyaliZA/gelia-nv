<?php

namespace App\Models;

use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudTraspasoRevisionProducto extends Model
{
    public const MOMENTO_ORIGEN = 'origen';

    public const MOMENTO_CEDIS = 'cedis';

    protected $table = 'solicitud_traspaso_revisiones_producto';

    protected $fillable = [
        'solicitud_traspaso_id',
        'momento',
        'solicitud_traspaso_producto_id',
        'producto_id',
        'sku',
        'orden',
        'descripcion_producto',
        'estado_fisico',
        'comentario',
        'unica_pieza',
        'mejor_ejemplar',
        'evidencia_paths',
        'registrado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'unica_pieza' => 'boolean',
            'mejor_ejemplar' => 'boolean',
            'evidencia_paths' => 'array',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTraspaso::class, 'solicitud_traspaso_id');
    }

    public function lineaSolicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTraspasoProducto::class, 'solicitud_traspaso_producto_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    public static function requiereEvidencia(string $estado): bool
    {
        return PedidoBmaRevisionProducto::requiereEvidencia($estado);
    }

    public static function requiereComentario(string $estado): bool
    {
        return PedidoBmaRevisionProducto::requiereComentario($estado);
    }
}
