<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;

class PedidoBmaAlertaPreparacion extends Model
{
    protected $table = 'pedido_bma_alertas_preparacion';

    protected $fillable = [
        'clave_unica',
        'pedido_bma_id',
        'pedido_bma_tarea_preparacion_id',
        'tipo',
        'ventana',
        'destinatarios',
        'error',
        'ejecutada_at',
    ];

    protected function casts(): array
    {
        return [
            'destinatarios' => 'array',
            'ejecutada_at' => 'datetime',
        ];
    }
}
