<?php

namespace App\Models\ControlPedidos;

use App\Models\Concerns\BelongsToUsuario;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoBmaTareaHistorial extends Model
{
    use BelongsToUsuario;

    protected $table = 'pedido_bma_tarea_historial';

    protected $fillable = [
        'pedido_bma_tarea_preparacion_id',
        'usuario_id',
        'estado_anterior',
        'estado_nuevo',
        'accion',
        'comentario',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
        ];
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaTareaPreparacion::class, 'pedido_bma_tarea_preparacion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsToUsuario('usuario_id');
    }
}
