<?php

namespace App\Notifications;

use App\Models\CobranzaAlerta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AlertaCobranza extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public CobranzaAlerta $alerta;
    public string $mensajeVoz;

    public function __construct(CobranzaAlerta $alerta, string $mensajeVoz)
    {
        $this->alerta = $alerta->loadMissing(['cliente', 'factura']);
        $this->mensajeVoz = $mensajeVoz;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    private function payload(): array
    {
        return [
            'alerta_cobranza_id' => $this->alerta->id,
            'cliente' => $this->alerta->cliente->nombre ?? null,
            'dias_atraso' => $this->alerta->dias_atraso,
            'monto' => $this->alerta->factura->monto ?? 0,
            'tipo' => $this->alerta->tipo ?? 'cobranza_vencimiento',
            'titulo' => 'Credibox · ' . ($this->alerta->cliente->nombre ?? 'Cliente'),
            'mensaje' => "Cliente {$this->alerta->cliente->nombre} tiene un saldo vencido hace {$this->alerta->dias_atraso} días",
            'mensaje_visible' => "Cliente {$this->alerta->cliente->nombre} tiene un saldo vencido hace {$this->alerta->dias_atraso} días",
            'mensaje_voz' => $this->mensajeVoz,
            'fecha' => now()->toDateTimeString(),
            'modulo' => 'cobranza',
        ];
    }
}
