<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\SolicitudTag;

class AlertaSolicitud extends Notification implements ShouldQueue
{
    use Queueable;

    public $solicitud;
    public $tipoAlerta; // 'nueva', 'aprobada', 'rechazada', 'pago_rechazado'
    public $mensaje;

    public function __construct(SolicitudTag $solicitud, $tipoAlerta, $mensaje)
    {
        $this->solicitud = $solicitud;
        $this->tipoAlerta = $tipoAlerta;
        $this->mensaje = $mensaje;
    }

    // Le decimos a Laravel que mande esto por Base de Datos (Campanita) y por Correo
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    // --- DISEÑO DEL CORREO ELECTRÓNICO ---
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
            $mail->error(); // Pone el botón rojo en el correo
        }

        return $mail;
    }

    // --- DISEÑO DE LA ALERTA WEB (Campanita) ---
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
}