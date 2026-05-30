<?php

namespace App\Jobs\Mensajeria;

use App\Events\AdjuntoProcesado;
use App\Models\MensajeAdjunto;
use App\Services\Mensajeria\GenerarThumbnailVideoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcesarAdjuntoMensajeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $adjuntoId,
    ) {}

    public function handle(GenerarThumbnailVideoService $thumbnailService): void
    {
        $adjunto = MensajeAdjunto::with('mensaje')->find($this->adjuntoId);

        if (!$adjunto || $adjunto->thumbnail_ruta) {
            return;
        }

        $thumbnail = $thumbnailService->ejecutar(
            $adjunto->ruta,
            $adjunto->mensaje->conversacion_id
        );

        if (!$thumbnail) {
            return;
        }

        $adjunto->update(['thumbnail_ruta' => $thumbnail]);
        $adjunto->refresh();

        broadcast(new AdjuntoProcesado(
            $adjunto->mensaje->conversacion_id,
            [
                'id' => $adjunto->id,
                'thumbnail_url' => $adjunto->thumbnailUrl(),
                'mensaje_id' => $adjunto->mensaje_id,
            ],
            $adjunto->mensaje_id,
        ))->toOthers();
    }
}
