<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class AlertasAumentoCreditoMasivoNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public array $alertas;

    /**
     * Create a new notification instance.
     *
     * @param array $alertas Arreglo de arreglos con keys: 'cliente', 'montoAnterior', 'montoNuevo'
     */
    public function __construct(array $alertas)
    {
        $this->alertas = $alertas;
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
        $mailMessage = (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('⚠️ Resumen: Aumentos de Crédito Irregulares (' . count($this->alertas) . ')')
            ->greeting('Hola,')
            ->line('Se detectaron ' . count($this->alertas) . ' aumentos de saldo en clientes cuyo periodo de crédito ya se encontraba vencido.')
            ->line('A continuación el listado de clientes afectados:');

        $table = '<table style="width: 100%; border-collapse: collapse; margin-top: 15px;">';
        $table .= '<thead>';
        $table .= '<tr>';
        $table .= '<th style="border-bottom: 1px solid #ddd; padding: 8px; text-align: left;">Cliente</th>';
        $table .= '<th style="border-bottom: 1px solid #ddd; padding: 8px; text-align: right;">Monto Anterior</th>';
        $table .= '<th style="border-bottom: 1px solid #ddd; padding: 8px; text-align: right;">Monto Actual</th>';
        $table .= '</tr>';
        $table .= '</thead>';
        $table .= '<tbody>';

        foreach ($this->alertas as $alerta) {
            $table .= '<tr>';
            $table .= '<td style="border-bottom: 1px solid #eee; padding: 8px;"><strong>' . $alerta['cliente']->numero_cliente . '</strong> - ' . $alerta['cliente']->nombre . '</td>';
            $table .= '<td style="border-bottom: 1px solid #eee; padding: 8px; text-align: right; color: #666;">$' . number_format($alerta['montoAnterior'], 2) . '</td>';
            $table .= '<td style="border-bottom: 1px solid #eee; padding: 8px; text-align: right; color: #dc2626; font-weight: bold;">$' . number_format($alerta['montoNuevo'], 2) . '</td>';
            $table .= '</tr>';
        }

        $table .= '</tbody></table><br>';

        $mailMessage->line(new HtmlString($table));
        $mailMessage->action('Ir a Credibox', url('/auto-cobranza'));
        $mailMessage->line('Por favor revisa estos casos a la brevedad en la pestaña de Alertas Operativas.');

        return $mailMessage;
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
        $count = count($this->alertas);
        $mensajeVoz = "Atención. Se han detectado {$count} aumentos de saldo en clientes con crédito vencido.";
        return [
            'cliente_id' => null, // Es masivo, no atado a un solo cliente
            'cliente' => 'Varios Clientes',
            'clientes_busqueda' => collect($this->alertas)->pluck('cliente.numero_cliente')->implode(','),
            'monto_anterior' => 0,
            'monto_nuevo' => 0,
            'tipo' => 'aumento_credito_masivo',
            'titulo' => 'Credibox · Aumentos de Crédito Irregulares',
            'mensaje' => "Se detectaron {$count} aumentos de saldo en clientes vencidos.",
            'mensaje_visible' => "Aumentos de deuda vencida detectados: {$count} clientes afectados.",
            'mensaje_voz' => $mensajeVoz,
            'fecha' => now()->toDateTimeString(),
            'modulo' => 'cobranza',
        ];
    }
}
