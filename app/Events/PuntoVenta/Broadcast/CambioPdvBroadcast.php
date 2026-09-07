<?php

namespace App\Events\PuntoVenta\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class CambioPdvBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  list<Channel>  $channels
     * @param  array<string, mixed>  $envelope
     */
    public function __construct(
        public array $channels,
        public array $envelope,
    ) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return $this->channels;
    }

    public function broadcastAs(): string
    {
        return (string) ($this->envelope['tipo'] ?? 'pdv.cambio');
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->envelope;
    }
}
