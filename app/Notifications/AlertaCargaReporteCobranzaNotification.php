<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class AlertaCargaReporteCobranzaNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public function __construct(
        public array $resultado,
        public ?User $usuarioCarga = null,
    ) {}

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
            ->subject('Credibox · Carga de reporte completada')
            ->greeting('Hola ' . $notifiable->name . ',')
            ->line('Se completó una carga de reporte de cobranza en Credibox.')
            ->line('Usuario: ' . ($this->usuarioCarga?->name ?? 'Sistema'))
            ->line('Fecha: ' . now()->format('d/m/Y H:i'))
            ->line('Clientes procesados: ' . ($this->resultado['procesados'] ?? 0))
            ->line('Clientes nuevos: ' . ($this->resultado['nuevos'] ?? 0))
            ->line('Créditos nuevos: ' . ($this->resultado['creditos_nuevos'] ?? 0))
            ->line('Registros actualizados: ' . ($this->resultado['actualizados'] ?? 0));

        $detalle = $this->resultado['creditos_nuevos_detalle'] ?? [];
        if (!empty($detalle)) {
            $table = '<table style="width: 100%; border-collapse: collapse; margin-top: 15px;">';
            $table .= '<thead><tr>';
            $table .= '<th style="border-bottom: 1px solid #ddd; padding: 8px; text-align: left;">Cliente</th>';
            $table .= '<th style="border-bottom: 1px solid #ddd; padding: 8px; text-align: right;">Monto</th>';
            $table .= '</tr></thead><tbody>';

            foreach ($detalle as $item) {
                $cliente = $item['cliente'];
                $table .= '<tr>';
                $table .= '<td style="border-bottom: 1px solid #eee; padding: 8px;"><strong>' . e($cliente->numero_cliente) . '</strong> - ' . e($cliente->nombre) . '</td>';
                $table .= '<td style="border-bottom: 1px solid #eee; padding: 8px; text-align: right; font-weight: bold;">$' . number_format($item['monto'], 2) . '</td>';
                $table .= '</tr>';
            }

            $table .= '</tbody></table><br>';
            $mailMessage->line(new HtmlString($table));
        }

        return $mailMessage
            ->action('Ir a Credibox', url('/auto-cobranza'))
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
        $creditosNuevos = (int) ($this->resultado['creditos_nuevos'] ?? 0);
        $procesados = (int) ($this->resultado['procesados'] ?? 0);

        return [
            'cliente_id' => null,
            'cliente' => 'Carga Credibox',
            'monto_anterior' => 0,
            'monto_nuevo' => 0,
            'tipo' => 'carga_reporte_cobranza',
            'titulo' => 'Credibox · Carga de reporte completada',
            'mensaje' => "Carga procesada: {$procesados} clientes, {$creditosNuevos} créditos nuevos.",
            'mensaje_visible' => "Reporte de cobranza cargado por " . ($this->usuarioCarga?->name ?? 'Sistema') . ".",
            'mensaje_voz' => "Atención. Se completó una carga de reporte de cobranza con {$creditosNuevos} créditos nuevos.",
            'fecha' => now()->toDateTimeString(),
            'modulo' => 'cobranza',
        ];
    }
}
