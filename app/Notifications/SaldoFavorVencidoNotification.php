<?php

namespace App\Notifications;

use App\Models\SaldosAFavor\SafCredito;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SaldoFavorVencidoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SafCredito $credito) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'saldos_favor.vencido',
            'titulo' => 'Saldo a favor vencido',
            'mensaje' => "Saldo a favor {$this->credito->folio} venció con remanente \${$this->credito->monto_disponible}.",
            'saf_credito_id' => $this->credito->id,
            'cliente_id' => $this->credito->cliente_id,
            'folio' => $this->credito->folio,
            'url' => '/saldos-favor/cuenta/'.$this->credito->cliente_id,
        ];
    }
}
