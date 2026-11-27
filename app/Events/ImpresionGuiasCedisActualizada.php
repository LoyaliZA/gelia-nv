<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ImpresionGuiasCedisActualizada implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('control-pedidos.operaciones');
    }

    public function broadcastAs(): string
    {
        return 'cedis.impresion-guias.actualizada';
    }
}
