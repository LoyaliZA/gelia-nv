<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class AlertaLimiteCreditoSuperadoMasivoNotification extends Notification implements ShouldQueue, ShouldBroadcastNow
{
    use Queueable;

    public array $alertas;

    /**
     * Create a new notification instance.
     *
     * @param array $alertas Arreglo de arreglos con keys: 'cliente', 'monto_actual', 'limite'
     */
    public function __construct(array $alertas)
    {
        $this->alertas = $alertas;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable)
    {
        $count = count($this->alertas);
        $mailMessage = (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('⚠️ Alerta Crítica: Límites de Crédito Superados (' . $count . ')')
            ->greeting('Hola,')
            ->line('Durante la evaluación de cobranza se detectaron ' . $count . ' clientes cuyo saldo actual supera su límite de crédito autorizado.')
            ->line('A continuación el listado de clientes detectados:');

        $table = '<table style="width: 100%; border-collapse: collapse; margin-top: 15px;">';
        $table .= '<thead>';
        $table .= '<tr>';
        $table .= '<th style="border-bottom: 1px solid #ddd; padding: 8px; text-align: left;">Cliente</th>';
        $table .= '<th style="border-bottom: 1px solid #ddd; padding: 8px; text-align: right;">Límite Autorizado</th>';
        $table .= '<th style="border-bottom: 1px solid #ddd; padding: 8px; text-align: right;">Deuda Actual</th>';
        $table .= '</tr>';
        $table .= '</thead>';
        $table .= '<tbody>';

        foreach ($this->alertas as $alerta) {
            $table .= '<tr>';
            $table .= '<td style="border-bottom: 1px solid #eee; padding: 8px;"><strong>' . $alerta['cliente']->numero_cliente . '</strong> - ' . $alerta['cliente']->nombre . '</td>';
            $table .= '<td style="border-bottom: 1px solid #eee; padding: 8px; text-align: right; color: #666;">$' . number_format($alerta['limite'], 2) . '</td>';
            $table .= '<td style="border-bottom: 1px solid #eee; padding: 8px; text-align: right; color: #dc2626; font-weight: bold;">$' . number_format($alerta['monto_actual'], 2) . '</td>';
            $table .= '</tr>';
        }

        $table .= '</tbody></table><br>';

        $mailMessage->line(new HtmlString($table));
        $mailMessage->action('Ir al Módulo de Cobranza', url('/auto-cobranza'));
        $mailMessage->line('Por favor verifica esta situación con los ejecutivos responsables a la brevedad.');

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
        $mensajeVoz = "Atención. Se han detectado {$count} clientes que exceden su límite de crédito autorizado.";
        return [
            'cliente_id' => null, // Es masivo
            'cliente' => 'Varios Clientes',
            'clientes_busqueda' => collect($this->alertas)->pluck('cliente.numero_cliente')->implode(','),
            'monto_anterior' => 0,
            'monto_nuevo' => 0,
            'titulo' => 'Alerta: Exceso de Límite Autorizado',
            'mensaje' => "Se detectaron {$count} clientes con deuda superior a su límite de crédito.",
            'mensaje_visible' => "Límites de crédito superados: {$count} clientes detectados.",
            'mensaje_voz' => $mensajeVoz,
            'fecha' => now()->toDateTimeString(),
            'modulo' => 'cobranza',
        ];
    }
}
