<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoBmaTareaProducto extends Model
{
    protected $table = 'pedido_bma_tarea_productos';

    protected $fillable = [
        'pedido_bma_tarea_preparacion_id',
        'pedido_bma_origen_id',
        'producto_id',
        'sku',
        'descripcion_snapshot',
        'cantidad_solicitada',
        'cantidad_encontrada',
        'estado_fisico',
        'observacion',
        'unica_pieza',
        'mejor_ejemplar',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_solicitada' => 'integer',
            'cantidad_encontrada' => 'integer',
            'orden' => 'integer',
            'producto_id' => 'integer',
            'unica_pieza' => 'boolean',
            'mejor_ejemplar' => 'boolean',
        ];
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaTareaPreparacion::class, 'pedido_bma_tarea_preparacion_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(PedidoBmaTareaDocumento::class, 'pedido_bma_tarea_producto_id');
    }
}
