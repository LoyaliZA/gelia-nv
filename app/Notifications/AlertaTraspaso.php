<?php

namespace App\Notifications;

use App\Models\SolicitudTraspaso;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertaTraspaso extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public SolicitudTraspaso $solicitud;
    public string $tipoAlerta;
    public string $mensaje;
    public string $titulo;
    public string $mensajeVisible;

    private const ETIQUETAS = [
        'nueva' => 'Nueva solicitud de traspaso',
        'respondida' => 'Traspaso generado',
        'rechazada' => 'Error en traspaso',
        'verificada' => 'Traspaso verificado',
    ];

    public function __construct(SolicitudTraspaso $solicitud, string $tipoAlerta, string $mensaje)
    {
        $this->solicitud = $solicitud->loadMissing(['vendedor', 'estado', 'cliente']);
        $this->tipoAlerta = $tipoAlerta;
        $this->mensaje = $mensaje;
        $this->titulo = (self::ETIQUETAS[$tipoAlerta] ?? 'Notificación de traspaso') . ' · ' . $solicitud->folio;
        $this->mensajeVisible = "{$mensaje} · {$solicitud->folio}";
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
            ->subject("GELIA · Traspaso · {$this->solicitud->folio}")
            ->greeting('Hola, ' . $notifiable->name . '!')
            ->line($this->titulo)
            ->line($this->mensajeVisible)
            ->action('Ver solicitud de traspaso', url('/traspasos?folio=' . $this->solicitud->folio));
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(array_merge(
            $this->payload(),
            ['mensaje_voz' => $this->construirMensajeVoz($notifiable)]
        ));
    }

    private function payload(): array
    {
        return [
            'solicitud_traspaso_id' => $this->solicitud->id,
            'folio' => $this->solicitud->folio,
            'tipo' => $this->tipoAlerta,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensajeVisible,
            'mensaje_visible' => $this->mensajeVisible,
            'estado' => $this->solicitud->estado->nombre ?? null,
            'vendedora' => $this->solicitud->vendedor->name ?? null,
            'fecha' => now()->toDateTimeString(),
            'modulo' => 'traspasos',
        ];
    }

    private function construirMensajeVoz(object $notifiable): string
    {
        $nombre = explode(' ', trim($notifiable->name))[0];
        $folio = $this->solicitud->folio;
        $vendedor = explode(' ', trim($this->solicitud->vendedor->name ?? 'un colaborador'))[0];

        return match ($this->tipoAlerta) {
            'nueva' => "Atención {$nombre}, {$vendedor} envió una solicitud de traspaso, {$folio}.",
            'respondida' => "{$nombre}, el traspaso {$folio} fue generado exitosamente.",
            'rechazada' => "{$nombre}, se reportó un error en la solicitud de traspaso {$folio}.",
            'verificada' => "{$nombre}, el traspaso {$folio} fue verificado correctamente.",
            default => "{$nombre}, tienes una notificación sobre el traspaso {$folio}.",
        };
    }
}
