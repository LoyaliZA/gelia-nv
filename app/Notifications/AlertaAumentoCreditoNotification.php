<?php

namespace App\Notifications;

use App\Models\Cliente;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AlertaAumentoCreditoNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public Cliente $cliente;
    public float $montoAnterior;
    public float $montoNuevo;

    public function __construct(Cliente $cliente, float $montoAnterior, float $montoNuevo)
    {
        $this->cliente = $cliente;
        $this->montoAnterior = $montoAnterior;
        $this->montoNuevo = $montoNuevo;
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (config('alertas.enviar_correo', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable)
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
                    ->subject('⚠️ Alerta: Aumento de Crédito sin Pago - ' . $this->cliente->nombre)
                    ->greeting('Hola,')
                    ->line("Se ha detectado un aumento en el saldo de crédito del cliente **{$this->cliente->nombre}**.")
                    ->line("El monto anterior era de **\${$this->montoAnterior}** y ahora el reporte indica **\${$this->montoNuevo}**.")
                    ->line('Esta alerta se genera porque no se han detectado pagos que justifiquen una nueva solicitud de crédito o el cliente sobrepasó su límite anómalo.')
                    ->action('Ver Cliente', url('/admin/clientes/' . $this->cliente->id))
                    ->line('Por favor revisa el caso a la brevedad.');
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
        $mensajeVoz = "Atención. El cliente {$this->cliente->nombre} aumentó su saldo de crédito sin pagar el saldo anterior.";
        return [
            'cliente_id' => $this->cliente->id,
            'cliente' => $this->cliente->nombre,
            'monto_anterior' => $this->montoAnterior,
            'monto_nuevo' => $this->montoNuevo,
            'tipo' => 'aumento_credito',
            'titulo' => 'Credibox · Aumento de Crédito · ' . $this->cliente->nombre,
            'mensaje' => "Se detectó un aumento de saldo (de \${$this->montoAnterior} a \${$this->montoNuevo}) sin pago previo.",
            'mensaje_visible' => "Aumento de deuda sin pago detectado: \${$this->montoAnterior} -> \${$this->montoNuevo}",
            'mensaje_voz' => $mensajeVoz,
            'fecha' => now()->toDateTimeString(),
            'modulo' => 'cobranza',
        ];
    }
}
