<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class AlertaPagoLiquidoNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public $clientesPagados;

    /**
     * Create a new notification instance.
     */
    public function __construct($clientesPagados)
    {
        $this->clientesPagados = $clientesPagados;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (config('alertas.enviar_correo', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('✅ Resumen: Pagos y Liquidaciones Recibidas (' . count($this->clientesPagados) . ')')
            ->greeting('Hola ' . $notifiable->name . ',')
            ->line('Se ha detectado el pago y liquidación de deuda de los siguientes clientes:')
            ->line('A continuación el listado:');

        $table = '<table style="width: 100%; border-collapse: collapse; margin-top: 15px;">';
        $table .= '<thead>';
        $table .= '<tr>';
        $table .= '<th style="border-bottom: 1px solid #ddd; padding: 8px; text-align: left;">Cliente</th>';
        $table .= '<th style="border-bottom: 1px solid #ddd; padding: 8px; text-align: right;">Monto Liquidado</th>';
        $table .= '</tr>';
        $table .= '</thead>';
        $table .= '<tbody>';

        foreach ($this->clientesPagados as $item) {
            $cliente = $item['cliente'];
            $monto = number_format($item['montoPagado'], 2);
            $table .= '<tr>';
            $table .= '<td style="border-bottom: 1px solid #eee; padding: 8px;"><strong>' . $cliente->numero_cliente . '</strong> - ' . $cliente->nombre . '</td>';
            $table .= '<td style="border-bottom: 1px solid #eee; padding: 8px; text-align: right; color: #16a34a; font-weight: bold;">$' . $monto . '</td>';
            $table .= '</tr>';
        }
        
        $table .= '</tbody></table><br>';

        return $mail
            ->line(new HtmlString($table))
            ->action('Ver Módulo de Auto-Cobranza', route('auto-cobranza.index'))
            ->line('Gracias por usar nuestro sistema automatizado.');
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
        $count = count($this->clientesPagados);
        $mensajeVoz = "Excelente. Se han detectado {$count} pagos liquidando deudas.";
        return [
            'cliente_id' => null,
            'cliente' => 'Varios Clientes',
            'clientes_busqueda' => collect($this->clientesPagados)->pluck('cliente.numero_cliente')->implode(','),
            'monto_anterior' => 0,
            'monto_nuevo' => 0,
            'tipo' => 'pago_liquidado',
            'titulo' => 'Liquidación de Créditos Detectada',
            'mensaje' => "Se detectaron {$count} liquidaciones de deuda de clientes.",
            'mensaje_visible' => "Pagos detectados: {$count} clientes liquidaron su saldo.",
            'mensaje_voz' => $mensajeVoz,
            'fecha' => now()->toDateTimeString(),
            'modulo' => 'cobranza',
        ];
    }
}
