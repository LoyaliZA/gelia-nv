<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MensajeEnviado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversacionId,
        public array $mensaje,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversacion.' . $this->conversacionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'mensaje.enviado';
    }

    public function broadcastWith(): array
    {
        return [
            'mensaje' => $this->mensaje,
            'conversacion_id' => $this->conversacionId,
        ];
    }
}
