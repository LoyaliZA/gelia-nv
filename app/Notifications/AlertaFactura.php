<?php

namespace App\Notifications;

use App\Models\SolicitudFactura;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertaFactura extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public SolicitudFactura $solicitud;
    public string $tipoAlerta;
    public string $mensaje;
    public string $titulo;
    public string $mensajeVisible;

    private const ETIQUETAS = [
        'nueva' => 'Nueva solicitud de factura',
        'respondida' => 'Factura emitida',
        'rechazada' => 'Error en factura',
        'verificada' => 'Factura verificada',
    ];

    public function __construct(SolicitudFactura $solicitud, string $tipoAlerta, string $mensaje)
    {
        $this->solicitud = $solicitud->loadMissing(['vendedor', 'estado']);
        $this->tipoAlerta = $tipoAlerta;
        $this->mensaje = $mensaje;
        $this->titulo = (self::ETIQUETAS[$tipoAlerta] ?? 'Notificación de factura') . ' · ' . $solicitud->folio;
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
            ->subject("GELIA · Factura · {$this->solicitud->folio}")
            ->greeting('Hola, ' . $notifiable->name . '!')
            ->line($this->titulo)
            ->line($this->mensajeVisible)
            ->action('Ver solicitud de factura', url('/facturas?folio=' . $this->solicitud->folio));
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
            'solicitud_factura_id' => $this->solicitud->id,
            'folio' => $this->solicitud->folio,
            'tipo' => $this->tipoAlerta,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensajeVisible,
            'mensaje_visible' => $this->mensajeVisible,
            'estado' => $this->solicitud->estado->nombre ?? null,
            'vendedora' => $this->solicitud->vendedor->name ?? null,
            'fecha' => now()->toDateTimeString(),
            'modulo' => 'facturas',
        ];
    }
}
