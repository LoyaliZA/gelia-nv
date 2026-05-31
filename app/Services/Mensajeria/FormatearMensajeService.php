<?php

namespace App\Services\Mensajeria;

use App\Models\Conversacion;
use App\Models\ConversacionParticipante;
use App\Models\Mensaje;
use App\Models\User;

class FormatearMensajeService
{
    public function ejecutar(Mensaje $mensaje, User $viewer, Conversacion $conversacion): array
    {
        $totalParticipantes = $conversacion->participantes()->count();
        $otrosParticipantes = max(0, $totalParticipantes - 1);
        $lecturasOtros = $mensaje->lecturas
            ->filter(fn ($l) => $l->user_id !== $mensaje->user_id)
            ->count();

        $estadoLectura = 'enviado';
        if ($mensaje->user_id === $viewer->id && $otrosParticipantes > 0) {
            if ($lecturasOtros >= $otrosParticipantes) {
                $estadoLectura = 'leido';
            } elseif ($lecturasOtros > 0) {
                $estadoLectura = 'entregado';
            }
        }

        return [
            'id' => $mensaje->id,
            'conversacion_id' => $mensaje->conversacion_id,
            'tipo' => $mensaje->tipo,
            'contenido' => $mensaje->contenido,
            'created_at' => $mensaje->created_at?->toIso8601String(),
            'es_propio' => $mensaje->user_id === $viewer->id,
            'estado_lectura' => $estadoLectura,
            'user' => [
                'id' => $mensaje->user?->id,
                'name' => $mensaje->user?->name,
                'username' => $mensaje->user?->username,
                'foto_perfil' => $mensaje->user?->foto_perfil,
            ],
            'reply_to' => $mensaje->replyTo ? $this->formatearReplyTo($mensaje->replyTo) : null,
            'adjuntos' => $mensaje->adjuntos->map(fn ($a) => [
                'id' => $a->id,
                'ruta' => $a->ruta,
                'url' => $a->url(),
                'thumbnail_url' => $a->thumbnailUrl(),
                'nombre_original' => $a->nombre_original,
                'mime' => $a->mime,
                'tamano' => $a->tamano,
                'duracion_seg' => $a->duracion_seg,
                'metadata' => $a->metadata,
            ])->values()->all(),
            'lecturas_count' => $lecturasOtros,
        ];
    }

    private function formatearReplyTo(Mensaje $replyTo): array
    {
        $adjunto = $replyTo->adjuntos->first();

        return [
            'id' => $replyTo->id,
            'contenido' => $replyTo->contenido,
            'tipo' => $replyTo->tipo,
            'preview' => $this->previewMensaje($replyTo, $adjunto),
            'nombre_adjunto' => $adjunto?->nombre_original,
            'user' => [
                'id' => $replyTo->user?->id,
                'name' => $replyTo->user?->name,
            ],
        ];
    }

    private function previewMensaje(Mensaje $mensaje, $adjunto = null): string
    {
        if ($mensaje->tipo === Mensaje::TIPO_TEXTO && $mensaje->contenido) {
            $texto = trim($mensaje->contenido);

            return mb_strlen($texto) > 120 ? mb_substr($texto, 0, 120) . '…' : $texto;
        }

        if ($adjunto?->nombre_original) {
            return $adjunto->nombre_original;
        }

        if ($mensaje->contenido) {
            $texto = trim($mensaje->contenido);

            return mb_strlen($texto) > 100 ? mb_substr($texto, 0, 100) . '…' : $texto;
        }

        return match ($mensaje->tipo) {
            Mensaje::TIPO_IMAGEN => '📷 Imagen',
            Mensaje::TIPO_VIDEO => '🎬 Video',
            Mensaje::TIPO_AUDIO => '🎤 Audio',
            Mensaje::TIPO_ARCHIVO => '📎 Archivo',
            default => '[mensaje]',
        };
    }

    /** Payload para broadcast: sin campos que dependen del usuario que ve el chat. */
    public function ejecutarParaBroadcast(Mensaje $mensaje, Conversacion $conversacion): array
    {
        $payload = $this->ejecutar($mensaje, $mensaje->user, $conversacion);
        unset($payload['es_propio'], $payload['estado_lectura']);

        return $payload;
    }
}
