<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class AlertaNuevoCreditoMasivoNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public array $nuevosCreditos;

    /**
     * Create a new notification instance.
     *
     * @param array $nuevosCreditos Arreglo con keys: 'cliente', 'monto'
     */
    public function __construct(array $nuevosCreditos)
    {
        $this->nuevosCreditos = $nuevosCreditos;
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
        $mailMessage = (new MailMessage)
            ->subject('🆕 Resumen: Nuevos Créditos Iniciados (' . count($this->nuevosCreditos) . ')')
            ->greeting('Hola ' . $notifiable->name . ',')
            ->line('Se detectó el inicio de crédito (saldo nuevo consolidado) para ' . count($this->nuevosCreditos) . ' clientes.')
            ->line('A continuación el listado:');

        $table = '<table style="width: 100%; border-collapse: collapse; margin-top: 15px;">';
        $table .= '<thead>';
        $table .= '<tr>';
        $table .= '<th style="border-bottom: 1px solid #ddd; padding: 8px; text-align: left;">Cliente</th>';
        $table .= '<th style="border-bottom: 1px solid #ddd; padding: 8px; text-align: right;">Monto Inicial</th>';
        $table .= '</tr>';
        $table .= '</thead>';
        $table .= '<tbody>';

        foreach ($this->nuevosCreditos as $item) {
            $table .= '<tr>';
            $table .= '<td style="border-bottom: 1px solid #eee; padding: 8px;"><strong>' . $item['cliente']->numero_cliente . '</strong> - ' . $item['cliente']->nombre . '</td>';
            $table .= '<td style="border-bottom: 1px solid #eee; padding: 8px; text-align: right; color: #0284c7; font-weight: bold;">$' . number_format($item['monto'], 2) . '</td>';
            $table .= '</tr>';
        }

        $table .= '</tbody></table><br>';

        $mailMessage->line(new HtmlString($table));
        $mailMessage->action('Ir a Credibox', url('/auto-cobranza'));
        $mailMessage->line('Gracias por usar nuestro sistema automatizado.');

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
        $count = count($this->nuevosCreditos);
        $mensajeVoz = "Atención. Se detectó el inicio de crédito en {$count} clientes.";
        return [
            'cliente_id' => null,
            'cliente' => 'Varios Clientes',
            'clientes_busqueda' => collect($this->nuevosCreditos)->pluck('cliente.numero_cliente')->implode(','),
            'monto_anterior' => 0,
            'monto_nuevo' => 0,
            'tipo' => 'nuevo_credito',
            'titulo' => 'Credibox · Nuevos Créditos Detectados',
            'mensaje' => "Se detectaron {$count} nuevos inicios de crédito.",
            'mensaje_visible' => "Nuevos saldos: {$count} clientes iniciaron su periodo de crédito.",
            'mensaje_voz' => $mensajeVoz,
            'fecha' => now()->toDateTimeString(),
            'modulo' => 'cobranza',
        ];
    }
}
