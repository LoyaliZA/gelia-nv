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
            $this->cerrarSinAplicar($solicitud);

            return;
        }
        if (! $ajuste->estaAutorizada($solicitud)) {
            $this->cerrarSinAplicar($solicitud);

            return;
        }
        try {
            $ajuste->aplicarBeneficios($solicitud);
            if (! $ajuste->estaAutorizada($solicitud)) {
                $this->cerrarSinAplicar($solicitud);

                return;
            }
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

    /** Cancelada, rechazada o vencida: no recupera beneficios ni se reintenta. */
    private function cerrarSinAplicar(SolicitudTag $solicitud): void
    {
        $solicitud->forceFill([
            'aplicacion_cliente' => 'no_aplica',
            'aplicacion_error' => 'La solicitud ya no está autorizada. No se aplicó ningún beneficio.',
        ])->save();
    }
}
