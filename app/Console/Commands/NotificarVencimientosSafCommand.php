<?php

namespace App\Console\Commands;

use App\Models\SaldosAFavor\SafCredito;
use App\Models\User;
use App\Notifications\SaldoFavorProximoVencerNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotificarVencimientosSafCommand extends Command
{
    protected $signature = 'saldos-favor:notificar-vencimientos {--dias=30 : Días hacia adelante para avisar}';

    protected $description = 'Notifica créditos SAF próximos a vencer (default 30 días).';

    public function handle(): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $hasta = now()->addDays($dias)->toDateString();

        $creditos = SafCredito::query()
            ->whereIn('estado_financiero', SafCredito::ESTADOS_USABLES)
            ->where('monto_disponible', '>', 0)
            ->whereDate('fecha_vencimiento', '<=', $hasta)
            ->whereDate('fecha_vencimiento', '>=', now()->toDateString())
            ->get();

        $revisores = User::permission('saldos_favor.revisar')->get();
        if ($revisores->isEmpty()) {
            $this->warn('Sin usuarios con saldos_favor.revisar.');

            return self::SUCCESS;
        }

        foreach ($creditos as $credito) {
            Notification::send($revisores, new SaldoFavorProximoVencerNotification($credito));
        }

        $this->info('Notificaciones enviadas: '.$creditos->count());

        return self::SUCCESS;
    }
}
