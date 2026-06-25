<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AlertaCobranzaVencimientoMasivoNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    /** @param array<int, array{cliente: \App\Models\Cliente, dias_atraso: int, monto: float}> */
    public function __construct(public array $clientesVencidos) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload($notifiable);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload($notifiable));
    }

    private function payload(object $notifiable): array
    {
        $count = count($this->clientesVencidos);
        $nombreDestinatario = explode(' ', trim($notifiable->name))[0];
        $clientesVoz = collect($this->clientesVencidos)->map(fn (array $item) => [
            'nombre' => $item['cliente']->nombre,
            'numero_cliente' => $item['cliente']->numero_cliente,
            'dias_atraso' => $item['dias_atraso'],
            'monto' => $item['monto'],
        ])->values()->all();

        return [
            'cliente_id' => null,
            'cliente' => 'Varios Clientes',
            'clientes_busqueda' => collect($clientesVoz)->pluck('numero_cliente')->implode(','),
            'clientes_vencidos' => $clientesVoz,
            'total_vencidos_cobranza' => $count,
            'tipo' => 'cobranza_vencimiento_masivo',
            'titulo' => 'Aviso de Cobranza · Saldos Vencidos',
            'mensaje' => "Se detectaron {$count} clientes con saldo vencido.",
            'mensaje_visible' => "Saldos vencidos: {$count} clientes pendientes de revisión.",
            'mensaje_voz' => "Atención {$nombreDestinatario}. Reporte de cobranza. Se detectaron {$count} clientes con saldo vencido.",
            'fecha' => now()->toDateTimeString(),
            'modulo' => 'cobranza',
        ];
    }
}
