<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PresenciaContactoActualizada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $destinatarioUserId,
        public array $presencia,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->destinatarioUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'presencia.contacto';
    }

    public function broadcastWith(): array
    {
        return [
            'presencia' => $this->presencia,
            'user_id' => $this->presencia['user_id'] ?? null,
        ];
    }
}
