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
            'mensaje_voz'  => $this->construirMensajeVoz($notifiable) // Inyección del TTS
        ]);
    }

    private function construirMensajeVoz(object $notifiable): string
    {
        $nombreDestinatario = explode(' ', trim($notifiable->name))[0];
        $nombreVendedor = explode(' ', trim($this->solicitud->vendedor->name ?? 'un colaborador'))[0];
        $esVendedorOriginal = ($this->solicitud->vendedor_id === $notifiable->id);
        
        // El guion cambia según la naturaleza de la alerta
        switch ($this->tipoAlerta) {
            case 'nueva':
                return "Atención {$nombreDestinatario}, {$nombreVendedor} ha realizado una nueva solicitud.";

            case 'rechazada':
            case 'pago_rechazado':
                if ($esVendedorOriginal) {
                    return "{$nombreDestinatario}, se ha encontrado un error en tu solicitud. Por favor, realiza las correcciones.";
                }
                // Guion para la Auxiliar/Encargada
                return "{$nombreDestinatario}, {$nombreVendedor} ha recibido una observación en su solicitud.";

            case 'reparada':
                return "Atención {$nombreDestinatario}, {$nombreVendedor} ha corregido su solicitud y está lista para revisión.";

            case 'pago_confirmado':
                return "{$nombreDestinatario}, {$nombreVendedor} ha confirmado el pago del cliente en el folio {$this->solicitud->id}.";

            case 'actualizacion':
                if ($esVendedorOriginal) {
                    return "{$nombreDestinatario}, el área administrativa respondió tu solicitud.";
                }
                return "{$nombreDestinatario}, {$nombreVendedor} tiene una nueva actualización en su folio.";

            default:
                return "{$nombreDestinatario}, tienes una notificación operativa de {$nombreVendedor}.";
        }
    }
}