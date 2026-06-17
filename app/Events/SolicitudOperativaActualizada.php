<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SolicitudOperativaActualizada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $solicitudId,
        public string $accion,
        public int $porUsuarioId,
        public ?int $vendedorId = null,
        public ?int $departamentoId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('solicitudes.operativas'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'solicitud-operativa.actualizada';
    }

    public function broadcastWith(): array
    {
        return [
            'solicitud_id' => $this->solicitudId,
            'accion' => $this->accion,
            'por_usuario_id' => $this->porUsuarioId,
            'vendedor_id' => $this->vendedorId,
            'departamento_id' => $this->departamentoId,
        ];
    }
}
