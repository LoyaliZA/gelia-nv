<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast; // <-- Agregado
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage; // <-- Agregado
use Illuminate\Notifications\Notification;
use App\Models\SolicitudTag;

// Agregamos ShouldBroadcast a la firma de la clase
class AlertaSolicitud extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public $solicitud;
    public $tipoAlerta; 
    public $mensaje;

    public function __construct(SolicitudTag $solicitud, $tipoAlerta, $mensaje)
    {
        $this->solicitud = $solicitud;
        $this->tipoAlerta = $tipoAlerta;
        $this->mensaje = $mensaje;
    }

    public function via(object $notifiable): array
    {
        // Agregamos 'broadcast' para que Laravel sepa que debe enviarlo por Reverb
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/solicitudes?folio=' . $this->solicitud->id);
        
        $mail = (new MailMessage)
                    ->subject('Alerta de Solicitud GELIA: FOL-' . $this->solicitud->id)
                    ->greeting('Hola, ' . $notifiable->name . '!')
                    ->line('Se ha registrado una actualización en el sistema:')
                    ->line('**Aviso:** ' . $this->mensaje)
                    ->line('**Cliente:** ' . ($this->solicitud->cliente->nombre ?? 'N/A'))
                    ->line('**Proceso:** ' . $this->solicitud->proceso->nombre)
                    ->action('Ver Solicitud en el ERP', $url);

        if ($this->tipoAlerta === 'pago_rechazado' || $this->tipoAlerta === 'rechazada') {
            $mail->error(); 
        }

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'solicitud_id' => $this->solicitud->id,
            'tipo'         => $this->tipoAlerta,
            'mensaje'      => $this->mensaje,
            'cliente'      => $this->solicitud->cliente->nombre ?? 'N/A',
            'fecha'        => now()->toDateTimeString(),
        ];
    }

    // --- NUEVO: DISEÑO DE LA ALERTA EN TIEMPO REAL (Reverb) ---
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'solicitud_id' => $this->solicitud->id,
            'tipo'         => $this->tipoAlerta,
            'mensaje'      => $this->mensaje,
            'cliente'      => $this->solicitud->cliente->nombre ?? 'N/A',
            'fecha'        => now()->toDateTimeString(),
        ]);
    }
}