<?php

namespace App\Notifications;

use App\Models\SaldosAFavor\SafIncidencia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SaldoFavorIncidenciaAbiertaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SafIncidencia $incidencia) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'saldos_favor.incidencia',
            'titulo' => 'Incidencia de saldo a favor',
            'mensaje' => $this->incidencia->descripcion,
            'saf_incidencia_id' => $this->incidencia->id,
            'cliente_id' => $this->incidencia->cliente_id,
            'pedido_bma_id' => $this->incidencia->pedido_bma_id,
            'url' => '/saldos-favor?tab=incidencias',
        ];
    }
}
