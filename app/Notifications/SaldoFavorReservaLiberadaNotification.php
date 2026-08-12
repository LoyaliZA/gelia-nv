<?php

namespace App\Notifications;

use App\Models\SaldosAFavor\SafCredito;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SaldoFavorReservaLiberadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SafCredito $credito,
        public float $monto,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'saldos_favor.reserva_liberada',
            'titulo' => 'Reserva de saldo liberada',
            'mensaje' => "Se liberó \${$this->monto} del saldo a favor {$this->credito->folio}.",
            'saf_credito_id' => $this->credito->id,
            'cliente_id' => $this->credito->cliente_id,
            'monto' => $this->monto,
            'url' => '/saldos-favor/cuenta/'.$this->credito->cliente_id,
        ];
    }
}
