<?php

namespace App\Events\Soporte;

use App\Models\SoporteTicketInteraccion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketInteraccionCreatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SoporteTicketInteraccion $interaccion;

    public function __construct(SoporteTicketInteraccion $interaccion)
    {
        $this->interaccion = $interaccion->loadMissing('user');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('soporte.ticket.' . $this->interaccion->ticket_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'interaccion.creada';
    }

    public function broadcastWith(): array
    {
        $user = $this->interaccion->user;

        return [
            'ticket_id' => $this->interaccion->ticket_id,
            'interaccion' => [
                'id' => $this->interaccion->id,
                'ticket_id' => $this->interaccion->ticket_id,
                'user_id' => $this->interaccion->user_id,
                'mensaje' => $this->interaccion->mensaje,
                'created_at' => $this->interaccion->created_at?->toIso8601String(),
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'profile_photo_url' => $user->profile_photo_url ?? null,
                ] : null,
            ],
        ];
    }
}
