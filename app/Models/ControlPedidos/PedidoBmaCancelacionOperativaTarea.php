<?php

namespace App\Models\ControlPedidos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoBmaCancelacionOperativaTarea extends Model
{
    public const LIBERACION_PENDIENTE = 'PENDIENTE';

    public const LIBERACION_LIBERADA = 'LIBERADA';

    public const LIBERACION_NO_SEPARADA = 'NO_SEPARADA';

    public const LIBERACION_INCIDENCIA = 'INCIDENCIA';

    protected $table = 'pedido_bma_cancelacion_operativa_tareas';

    protected $fillable = [
        'pedido_bma_cancelacion_operativa_id',
        'pedido_bma_tarea_preparacion_id',
        'estado_liberacion',
        'estado_previo_liberacion',
        'cantidad_a_liberar',
        'cantidad_liberada',
        'incidencia',
        'evidencia_meta',
        'liberada_por_id',
        'liberada_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'evidencia_meta' => 'array',
            'liberada_at' => 'datetime',
            'cantidad_a_liberar' => 'integer',
            'cantidad_liberada' => 'integer',
            'version' => 'integer',
        ];
    }

    public function cancelacion(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaCancelacionOperativa::class, 'pedido_bma_cancelacion_operativa_id');
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaTareaPreparacion::class, 'pedido_bma_tarea_preparacion_id');
    }

    public function liberadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liberada_por_id');
    }

    public function estaResuelta(): bool
    {
        return in_array($this->estado_liberacion, [
            self::LIBERACION_LIBERADA,
            self::LIBERACION_NO_SEPARADA,
        ], true);
    }
}
