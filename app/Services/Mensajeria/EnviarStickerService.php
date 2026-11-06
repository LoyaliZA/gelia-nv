<?php

namespace App\Services\Mensajeria;

use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\MensajeAdjunto;
use App\Models\MensajeriaSticker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EnviarStickerService
{
    public function __construct(
        private EnviarMensajeService $enviarMensaje,
        private FormatearMensajeService $formatearMensaje,
        private NotificarMensajeEnviadoService $notificarMensaje,
    ) {}

    public function ejecutar(Conversacion $conversacion, User $user, int $stickerId, ?int $replyToId = null): array
    {
        $sticker = MensajeriaSticker::query()->find($stickerId);

        if (! $sticker) {
            throw ValidationException::withMessages([
                'sticker_id' => 'El sticker no existe.',
            ]);
        }

        if ((int) $sticker->user_id !== (int) $user->id) {
            abort(403);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($sticker->ruta)) {
            throw ValidationException::withMessages([
                'sticker_id' => 'El sticker ya no está disponible.',
            ]);
        }

        $ext = pathinfo($sticker->ruta, PATHINFO_EXTENSION) ?: 'webp';
        $destino = "mensajeria/{$conversacion->id}/".Str::uuid().'.'.$ext;
        $disk->copy($sticker->ruta, $destino);

        $mensajeId = DB::transaction(function () use ($conversacion, $user, $replyToId, $destino, $sticker, $ext) {
            $mensaje = $this->enviarMensaje->ejecutar($conversacion, $user, [
                'tipo' => Mensaje::TIPO_STICKER,
                'contenido' => null,
                'reply_to_id' => $replyToId,
            ], broadcast: false);

            MensajeAdjunto::create([
                'mensaje_id' => $mensaje['id'],
                'ruta' => $destino,
                'thumbnail_ruta' => $destino,
                'nombre_original' => 'sticker.'.$ext,
                'mime' => $sticker->mime,
                'tamano' => $sticker->tamano,
            ]);

            return $mensaje['id'];
        });

        $mensaje = Mensaje::with([
            'user:id,name,foto_perfil',
            'adjuntos',
            'replyTo.user:id,name',
            'replyTo.adjuntos',
            'lecturas',
        ])->findOrFail($mensajeId);

        $formateado = $this->formatearMensaje->ejecutar($mensaje, $user, $conversacion);

        $this->notificarMensaje->ejecutar(
            $conversacion,
            $user,
            $this->formatearMensaje->ejecutarParaBroadcast($mensaje, $conversacion)
        );

        return $formateado;
    }
}
