<?php

namespace App\Notifications;

use App\Models\SaldosAFavor\SafComprobanteCaja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SaldoFavorComprobantePdvNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SafComprobanteCaja $comprobante) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'saldos_favor.comprobante_pdv',
            'titulo' => 'Comprobante PDV pendiente',
            'mensaje' => "Comprobante {$this->comprobante->folio} en estado {$this->comprobante->estado}.",
            'saf_comprobante_caja_id' => $this->comprobante->id,
            'cliente_id' => $this->comprobante->cliente_id,
            'folio' => $this->comprobante->folio,
            'url' => '/saldos-favor/caja/comprobante/'.$this->comprobante->id,
        ];
    }
}
