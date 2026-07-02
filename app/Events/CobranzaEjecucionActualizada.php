<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CobranzaEjecucionActualizada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $accion,
        public int $porUsuarioId,
        public ?int $clienteId = null,
        public ?int $alertaId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('cobranza.ejecucion'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'cobranza-ejecucion.actualizada';
    }

    public function broadcastWith(): array
    {
        return [
            'accion' => $this->accion,
            'por_usuario_id' => $this->porUsuarioId,
            'cliente_id' => $this->clienteId,
            'alerta_id' => $this->alertaId,
        ];
    }
}
