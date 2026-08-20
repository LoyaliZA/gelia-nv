<?php

namespace App\Events;

use App\Models\ControlPedidos\PedidoBmaSesionEvidencia;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EvidenciaCedisActualizada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $foto
     */
    public function __construct(
        public int $pedidoId,
        public int $sesionId,
        public string $tipo,
        public array $foto = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('pedido-bma.'.$this->pedidoId.'.evidencias'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'evidencia-cedis.actualizada';
    }

    public function broadcastWith(): array
    {
        return [
            'pedido_id' => $this->pedidoId,
            'sesion_id' => $this->sesionId,
            'tipo' => $this->tipo,
            'foto' => $this->foto,
        ];
    }

    public static function deSesion(PedidoBmaSesionEvidencia $sesion, string $tipo, array $foto = []): self
    {
        return new self((int) $sesion->pedido_bma_id, (int) $sesion->id, $tipo, $foto);
    }
}
