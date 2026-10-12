<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class PedidoBmaActualizado implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $pedidoId, public string $accion, public ?int $tareaId = null)
    {
        $this->dontBroadcastToCurrentUser();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('control-pedidos.operaciones')];
    }

    public function broadcastAs(): string
    {
        return 'pedido.actualizado';
    }

    public function broadcastWith(): array
    {
        return ['pedido_id' => $this->pedidoId, 'tarea_id' => $this->tareaId, 'accion' => $this->accion];
    }
}
