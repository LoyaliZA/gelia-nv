<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Models\SolicitudTag;

class AlertaSolicitud extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public $solicitud;
    public $tipoAlerta;
    public $mensaje;
    public $extras;

    public function __construct(SolicitudTag $solicitud, $tipoAlerta, $mensaje, array $extras = [])
    {
        $this->solicitud = $solicitud->loadMissing(['cliente', 'proceso', 'estado', 'vendedor', 'listaDescuento']);
        $this->tipoAlerta = $tipoAlerta;
        $this->mensaje = $mensaje;
        $this->extras = $extras;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/solicitudes?folio=' . $this->solicitud->id);
        $payload = $this->construirPayload();

        $mail = (new MailMessage)
            ->subject('Alerta de Solicitud GELIA: FOL-' . $this->solicitud->id)
            ->greeting('Hola, ' . $notifiable->name . '!')
            ->line('Se ha registrado una actualización en el sistema:')
            ->line('**Aviso:** ' . $this->mensaje)
            ->line('**Cliente:** ' . ($payload['cliente'] ?? 'N/A'))
            ->line('**Proceso:** ' . ($payload['proceso'] ?? 'N/A'))
            ->line('**Estado:** ' . ($payload['estado'] ?? 'N/A'))
            ->action('Ver Solicitud en el ERP', $url);

        if (in_array($this->tipoAlerta, ['pago_rechazado', 'rechazada', 'alerta_pago_insuficiente'], true)) {
            $mail->error();
        }

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->construirPayload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(array_merge(
            $this->construirPayload(),
            ['mensaje_voz' => $this->construirMensajeVoz($notifiable)]
        ));
    }

    private function construirPayload(): array
    {
        $cliente = $this->solicitud->cliente;

        return array_merge([
            'solicitud_id' => $this->solicitud->id,
            'tipo' => $this->tipoAlerta,
            'mensaje' => $this->mensaje,
            'cliente' => $cliente->nombre ?? 'N/A',
            'cliente_numero' => $cliente->numero_cliente ?? null,
            'proceso' => $this->solicitud->proceso->nombre ?? null,
            'estado' => $this->solicitud->estado->nombre ?? null,
            'vendedora' => $this->solicitud->vendedor->name ?? null,
            'monto' => $this->solicitud->monto_cotizado,
            'lista_solicitada' => $this->solicitud->listaDescuento->nombre ?? null,
            'fecha' => now()->toDateTimeString(),
        ], $this->extras);
    }

    private function construirMensajeVoz(object $notifiable): string
    {
        $nombreDestinatario = explode(' ', trim($notifiable->name))[0];
        $nombreVendedor = explode(' ', trim($this->solicitud->vendedor->name ?? 'un colaborador'))[0];
        $esVendedorOriginal = ($this->solicitud->vendedor_id === $notifiable->id);

        switch ($this->tipoAlerta) {
            case 'nueva':
                return "Atención {$nombreDestinatario}, {$nombreVendedor} ha realizado una nueva solicitud.";
            case 'alerta_ascenso_lista':
                return "Atención {$nombreDestinatario}, el pago procesado por {$nombreVendedor} ha permitido ascender al cliente de categoría.";
            case 'rechazada':
            case 'pago_rechazado':
                if ($esVendedorOriginal) {
                    return "{$nombreDestinatario}, se ha encontrado un error en tu solicitud. Por favor, inicia una nueva solicitud si el pago venció.";
                }
                return "{$nombreDestinatario}, {$nombreVendedor} ha recibido una observación en su solicitud.";
            case 'reparada':
                return "Atención {$nombreDestinatario}, {$nombreVendedor} ha corregido su solicitud y está lista para revisión.";
            case 'pago_confirmado':
                return "{$nombreDestinatario}, {$nombreVendedor} ha confirmado el pago del cliente en el folio {$this->solicitud->id}.";
            case 'consulta_nueva':
                return "Atención {$nombreDestinatario}, {$nombreVendedor} solicita verificación de TAG o lista antes de confirmar el pago.";
            case 'consulta_respondida':
                $resultado = ($this->extras['respuesta_positiva'] ?? false) ? 'confirmada' : 'rechazada';
                return "{$nombreDestinatario}, tu consulta de TAG o lista fue {$resultado}.";
            case 'rollback_confirmado':
                return "{$nombreDestinatario}, la encargada confirmó la reversión del folio vencido. Debes iniciar una nueva solicitud.";
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
