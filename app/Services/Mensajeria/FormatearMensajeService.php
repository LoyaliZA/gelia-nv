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
        if ($mensaje->user_id === $viewer->id) {
            if ($otrosParticipantes > 0 && $lecturasOtros >= $otrosParticipantes) {
                $estadoLectura = 'leido';
            } elseif ($otrosParticipantes > 0) {
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
                'foto_perfil' => $mensaje->user?->foto_perfil,
            ],
            'reply_to' => $mensaje->replyTo ? [
                'id' => $mensaje->replyTo->id,
                'contenido' => $mensaje->replyTo->contenido,
                'tipo' => $mensaje->replyTo->tipo,
                'user' => ['name' => $mensaje->replyTo->user?->name],
            ] : null,
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

    /** Payload para broadcast: sin campos que dependen del usuario que ve el chat. */
    public function ejecutarParaBroadcast(Mensaje $mensaje, Conversacion $conversacion): array
    {
        $payload = $this->ejecutar($mensaje, $mensaje->user, $conversacion);
        unset($payload['es_propio'], $payload['estado_lectura']);

        return $payload;
    }
}
