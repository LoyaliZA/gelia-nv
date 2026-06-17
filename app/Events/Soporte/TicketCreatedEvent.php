<?php

namespace App\Events\Soporte;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\SoporteTicket;

class TicketCreatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $ticket;

    /**
     * Create a new event instance.
     */
    public function __construct(SoporteTicket $ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('soporte.agentes'),
        ];
    }

    public function broadcastWith(): array
    {
        $this->ticket->loadMissing(['modulo', 'categoria', 'estado', 'prioridadAsignada', 'user', 'asignadoA']);

        return [
            'ticket' => [
                'id' => $this->ticket->id,
                'titulo' => $this->ticket->titulo,
                'user' => $this->ticket->user,
                'modulo' => $this->ticket->modulo,
                'categoria' => $this->ticket->categoria,
                'estado' => $this->ticket->estado,
                'prioridadAsignada' => $this->ticket->prioridadAsignada,
                'asignadoA' => $this->ticket->asignadoA,
                'created_at' => $this->ticket->created_at,
            ],
        ];
    }
}
