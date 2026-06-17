<?php

namespace App\Notifications\Soporte;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\SoporteTicket;

class TicketCreatedNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public SoporteTicket $ticket;

    public string $titulo;

    public string $mensajeVisible;

    public function __construct(SoporteTicket $ticket)
    {
        $this->ticket = $ticket->loadMissing(['user', 'modulo', 'categoria', 'estado', 'prioridadAsignada']);
        $this->titulo = "Nuevo ticket asignado #{$this->ticket->id}";
        $this->mensajeVisible = "Ticket #{$this->ticket->id}: {$this->ticket->titulo}";
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (config('alertas.enviar_correo', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Nuevo Ticket Asignado #{$this->ticket->id}: {$this->ticket->titulo}")
            ->greeting("Hola {$notifiable->name},")
            ->line('Se te ha asignado automáticamente un nuevo ticket de soporte.')
            ->line('**Módulo:** ' . ($this->ticket->modulo->nombre ?? 'N/A'))
            ->line('**Usuario:** ' . ($this->ticket->user->name ?? 'N/A'))
            ->line('**Descripción:** ' . $this->ticket->descripcion)
            ->action('Ver Ticket', url('/soporte/agente/tickets'))
            ->line('Por favor atiende este ticket a la brevedad posible.');
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload($notifiable);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload($notifiable));
    }

    private function payload(object $notifiable): array
    {
        $nombre = explode(' ', trim($notifiable->name))[0];
        $mensajePersonalizado = "{$nombre}, tienes una actualización en tu ticket de soporte.";

        return [
            'tipo' => 'soporte_ticket_nuevo',
            'ticket_id' => $this->ticket->id,
            'titulo' => $this->titulo,
            'mensaje' => $mensajePersonalizado,
            'mensaje_visible' => $mensajePersonalizado,
            'mensaje_voz' => $mensajePersonalizado,
            'url' => '/soporte/agente/tickets',
            'modulo' => 'soporte',
            'usuario_solicitante' => $this->ticket->user->name ?? null,
            'modulo_ticket' => $this->ticket->modulo->nombre ?? null,
            'fecha' => now()->toDateTimeString(),
        ];
    }
}
