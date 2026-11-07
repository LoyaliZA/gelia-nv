<?php

namespace App\Jobs\Mensajeria;

use App\Events\AdjuntoProcesado;
use App\Models\MensajeAdjunto;
use App\Services\Mensajeria\GenerarThumbnailVideoService;
use App\Services\Mensajeria\IndexarTextoAdjuntoService;
use App\Services\Mensajeria\OptimizarImagenMensajeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ProcesarAdjuntoMensajeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $adjuntoId,
    ) {}

    public function handle(
        GenerarThumbnailVideoService $thumbnailService,
        IndexarTextoAdjuntoService $indexarTexto,
        OptimizarImagenMensajeService $optimizarImagen,
    ): void {
        $adjunto = MensajeAdjunto::with('mensaje')->find($this->adjuntoId);

        if (!$adjunto) {
            return;
        }

        if ($adjunto->mensaje?->tipo === 'imagen' && !$adjunto->thumbnail_ruta) {
            $optimizado = $optimizarImagen->ejecutarDesdeAlmacen(
                $adjunto->ruta,
                $adjunto->mensaje->conversacion_id
            );

            if ($optimizado) {
                $anterior = $adjunto->ruta;
                $adjunto->update([
                    'ruta' => $optimizado['ruta'],
                    'thumbnail_ruta' => $optimizado['thumbnail_ruta'],
                    'mime' => $optimizado['mime'],
                    'tamano' => $optimizado['tamano'],
                ]);
                if ($anterior !== $optimizado['ruta']) {
                    Storage::disk('public')->delete($anterior);
                }
                $adjunto->refresh();
                $this->avisar($adjunto);
            }
        }

        if ($adjunto->mensaje?->tipo === 'video' && !$adjunto->thumbnail_ruta) {
            $thumbnail = $thumbnailService->ejecutar(
                $adjunto->ruta,
                $adjunto->mensaje->conversacion_id
            );

            if ($thumbnail) {
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

        if (!filled($adjunto->contenido_indexado)) {
            $indexarTexto->ejecutar($adjunto);
        }
    }

    private function avisar(MensajeAdjunto $adjunto): void
    {
        broadcast(new AdjuntoProcesado(
            $adjunto->mensaje->conversacion_id,
            [
                'id' => $adjunto->id,
                'url' => $adjunto->url(),
                'thumbnail_url' => $adjunto->thumbnailUrl(),
                'mime' => $adjunto->mime,
                'tamano' => $adjunto->tamano,
                'mensaje_id' => $adjunto->mensaje_id,
            ],
            $adjunto->mensaje_id,
        ))->toOthers();
    }
}
