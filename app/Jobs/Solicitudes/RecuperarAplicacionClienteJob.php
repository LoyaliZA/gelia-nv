<?php

namespace App\Jobs\Solicitudes;

use App\Models\SolicitudTag;
use App\Services\Solicitudes\AjustarMontoPorSolicitudService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RecuperarAplicacionClienteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $solicitudId) {}

    public function handle(AjustarMontoPorSolicitudService $ajuste): void
    {
        $solicitud = SolicitudTag::query()->find($this->solicitudId);
        if (! $solicitud || ! in_array($solicitud->aplicacion_cliente, ['pendiente', 'fallida'], true)) {
            return;
        }
        if ((int) $solicitud->aplicacion_intentos >= 5) {
            return;
        }
        if ($solicitud->motivo_incorrecta === 'vencimiento_pago') {
            return;
        }
        try {
            $ajuste->aplicarBeneficios($solicitud);
            $solicitud->forceFill(['aplicacion_cliente' => 'aplicada', 'aplicacion_error' => null])->save();
        } catch (Throwable $e) {
            $solicitud->forceFill([
                'aplicacion_cliente' => 'fallida',
                'aplicacion_error' => mb_substr($e->getMessage(), 0, 500),
            ])->save();
            $solicitud->increment('aplicacion_intentos');
            if ((int) $solicitud->fresh()->aplicacion_intentos < 5) {
                self::dispatch($solicitud->id)->delay(now()->addSeconds(30));
            }
        }
    }
}
