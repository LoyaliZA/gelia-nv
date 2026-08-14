<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMovimiento;
use App\Models\User;
use App\Notifications\SaldoFavorVencidoNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class VencerCreditosSafService
{
    public function __construct(
        private RegistrarMovimientoSafService $movimientos,
    ) {}

    public function handle(?\Carbon\CarbonInterface $hoy = null): int
    {
        $hoy = $hoy ?? now();
        $ids = SafCredito::query()
            ->whereIn('estado_financiero', SafCredito::ESTADOS_USABLES)
            ->where('monto_disponible', '>', 0)
            ->whereDate('fecha_vencimiento', '<', $hoy->toDateString())
            ->pluck('id');

        $count = 0;
        $revisores = User::permission('saldos_favor.revisar')->get();

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$count, $revisores) {
                $credito = SafCredito::whereKey($id)->lockForUpdate()->first();
                if (! $credito) {
                    return;
                }
                if (! in_array($credito->estado_financiero, SafCredito::ESTADOS_USABLES, true)) {
                    return;
                }
                if ((float) $credito->monto_disponible <= 0) {
                    return;
                }
                if ($credito->fecha_vencimiento->gte(now()->startOfDay())) {
                    return;
                }

                $antes = (float) $credito->monto_disponible;
                $credito->estado_financiero = SafCredito::ESTADO_VENCIDO;
                $credito->save();

                $this->movimientos->handle(
                    $credito,
                    SafMovimiento::TIPO_VENCIMIENTO,
                    $antes,
                    $antes,
                    $antes,
                    null,
                    ['observaciones' => 'Vencimiento automático por vigencia configurada']
                );

                if ($revisores->isNotEmpty()) {
                    Notification::send($revisores, new SaldoFavorVencidoNotification($credito->fresh()));
                }
                $count++;
            });
        }

        return $count;
    }
}
