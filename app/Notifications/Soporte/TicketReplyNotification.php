<?php

namespace App\Notifications\Soporte;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\SoporteTicketInteraccion;

class TicketReplyNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public SoporteTicketInteraccion $interaccion;

    public bool $isAgentReply;

    public string $titulo;

    public string $mensajeVisible;

    public function __construct(SoporteTicketInteraccion $interaccion, bool $isAgentReply = false)
    {
        $this->interaccion = $interaccion->loadMissing(['ticket.user', 'ticket.modulo', 'user']);
        $this->isAgentReply = $isAgentReply;
        $this->titulo = 'Actualización en ticket de soporte';
        $this->mensajeVisible = 'Tienes una actualización en tu ticket de soporte';
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
        $ticket = $this->interaccion->ticket;

        $linea = $this->isAgentReply
            ? 'El equipo de soporte ha respondido a tu ticket.'
            : "El usuario {$this->interaccion->user->name} ha agregado un nuevo comentario al ticket.";

        $url = $this->isAgentReply
            ? url('/soporte/mis-tickets')
            : url('/soporte/agente/tickets');

        return (new MailMessage)
            ->subject("Nuevo mensaje en Ticket #{$ticket->id}: {$ticket->titulo}")
            ->greeting("Hola {$notifiable->name},")
            ->line($linea)
            ->line('Mensaje:')
            ->line($this->interaccion->mensaje)
            ->action('Ver Ticket', $url)
            ->line('Gracias por usar nuestro sistema de soporte.');
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
        $ticket = $this->interaccion->ticket;
        $nombre = explode(' ', trim($notifiable->name))[0];
        $mensajePersonalizado = "{$nombre}, tienes una actualización en tu ticket de soporte.";

        return [
            'tipo' => $this->isAgentReply ? 'soporte_respuesta_agente' : 'soporte_respuesta_usuario',
            'ticket_id' => $ticket->id,
            'interaccion_id' => $this->interaccion->id,
            'titulo' => $this->titulo,
            'mensaje' => $mensajePersonalizado,
            'mensaje_visible' => $mensajePersonalizado,
            'mensaje_voz' => $mensajePersonalizado,
            'url' => $this->isAgentReply ? '/soporte/mis-tickets' : '/soporte/agente/tickets',
            'modulo' => 'soporte',
            'is_agent_reply' => $this->isAgentReply,
            'autor' => $this->interaccion->user->name ?? null,
            'ticket_titulo' => $ticket->titulo,
            'fecha' => now()->toDateTimeString(),
        ];
    }
}
