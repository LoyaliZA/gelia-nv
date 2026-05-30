<?php

namespace App\Services\Mensajeria;

use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnviarMensajeService
{
    public function __construct(
        private FormatearMensajeService $formatearMensaje,
        private NotificarMensajeEnviadoService $notificarMensaje,
    ) {}

    public function ejecutar(Conversacion $conversacion, User $user, array $data, bool $broadcast = true): array
    {
        $mensaje = DB::transaction(function () use ($conversacion, $user, $data) {
            $mensaje = Mensaje::create([
                'conversacion_id' => $conversacion->id,
                'user_id' => $user->id,
                'tipo' => $data['tipo'] ?? Mensaje::TIPO_TEXTO,
                'contenido' => $data['contenido'] ?? null,
                'reply_to_id' => $data['reply_to_id'] ?? null,
            ]);

            $preview = $this->construirPreview($mensaje);
            $conversacion->update([
                'ultimo_mensaje_at' => $mensaje->created_at,
                'ultimo_mensaje_preview' => $preview,
            ]);

            return $mensaje;
        });

        $mensaje->load(['user:id,name,foto_perfil', 'adjuntos', 'replyTo.user:id,name', 'lecturas']);

        $formateado = $this->formatearMensaje->ejecutar($mensaje, $user, $conversacion);

        if ($broadcast) {
            $this->notificarMensaje->ejecutar(
                $conversacion,
                $user,
                $this->formatearMensaje->ejecutarParaBroadcast($mensaje, $conversacion)
            );
        }

        return $formateado;
    }

    private function construirPreview(Mensaje $mensaje): string
    {
        return match ($mensaje->tipo) {
            Mensaje::TIPO_IMAGEN => '📷 Imagen',
            Mensaje::TIPO_VIDEO => '🎬 Video',
            Mensaje::TIPO_AUDIO => '🎤 Audio',
            Mensaje::TIPO_ARCHIVO => '📎 Archivo',
            default => mb_substr($mensaje->contenido ?? '', 0, 200),
        };
    }
}
