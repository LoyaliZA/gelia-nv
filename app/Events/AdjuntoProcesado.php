<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdjuntoProcesado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversacionId,
        public array $adjunto,
        public int $mensajeId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversacion.' . $this->conversacionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'adjunto.procesado';
    }

    public function broadcastWith(): array
    {
        return [
            'adjunto' => $this->adjunto,
            'mensaje_id' => $this->mensajeId,
            'conversacion_id' => $this->conversacionId,
        ];
    }
}
