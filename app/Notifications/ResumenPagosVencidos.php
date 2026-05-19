<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ResumenPagosVencidos extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public $totalVencidos;

    public function __construct(int $totalVencidos)
    {
        $this->totalVencidos = $totalVencidos;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'total_vencidos' => $this->totalVencidos,
            'mensaje' => "Se han vencido {$this->totalVencidos} solicitudes por falta de pago.",
            'fecha' => now()->toDateTimeString(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'total_vencidos' => $this->totalVencidos,
            'mensaje' => "Se han vencido {$this->totalVencidos} solicitudes por falta de pago.",
            'fecha' => now()->toDateTimeString(),
            'mensaje_voz' => $this->construirMensajeVoz($notifiable)
        ]);
    }

    private function construirMensajeVoz(object $notifiable): string
    {
        $nombreDestinatario = explode(' ', trim($notifiable->name))[0];
        
        return "Atención {$nombreDestinatario}, se han vencido {$this->totalVencidos} solicitudes de pago. No se reportaron depósitos de clientes. Favor de revisar y revertir los cambios correspondientes en el sistema.";
    }
}