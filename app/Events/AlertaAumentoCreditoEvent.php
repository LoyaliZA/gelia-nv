<?php

namespace App\Events;

use App\Models\Cliente;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertaAumentoCreditoEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Cliente $cliente;
    public float $montoAnterior;
    public float $montoNuevo;

    public function __construct(Cliente $cliente, float $montoAnterior, float $montoNuevo)
    {
        $this->cliente = $cliente;
        $this->montoAnterior = $montoAnterior;
        $this->montoNuevo = $montoNuevo;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('cobranza-alertas'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'AlertaAumentoCreditoEvent';
    }

    public function broadcastWith(): array
    {
        return [
            'cliente_id' => $this->cliente->id,
            'nombre' => $this->cliente->nombre,
            'numero_cliente' => $this->cliente->numero_cliente,
            'monto_anterior' => $this->montoAnterior,
            'monto_nuevo' => $this->montoNuevo,
            'mensaje' => 'Aumento de saldo detectado',
        ];
    }
}
