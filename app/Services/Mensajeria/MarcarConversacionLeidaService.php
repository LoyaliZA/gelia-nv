<?php

namespace App\Services\Mensajeria;

use App\Models\Conversacion;
use App\Models\ConversacionParticipante;
use App\Models\Mensaje;
use App\Models\User;

class MarcarConversacionLeidaService
{
    public function __construct(
        private RegistrarLecturaMensajeService $registrarLectura,
        private FormatearMensajeService $formatearMensaje,
        private NotificarCursorLecturaService $notificarCursor,
    ) {}

    public function ejecutar(Conversacion $conversacion, User $user): void
    {
        $ahora = now();

        ConversacionParticipante::where('conversacion_id', $conversacion->id)
            ->where('user_id', $user->id)
            ->update(['ultimo_leido_at' => $ahora]);

        $mensajeIds = Mensaje::query()
            ->where('conversacion_id', $conversacion->id)
            ->where('user_id', '!=', $user->id)
            ->whereDoesntHave('lecturas', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('id')
            ->pluck('id');

        foreach ($mensajeIds as $mensajeId) {
            $this->registrarLectura->ejecutar((int) $mensajeId, $user, emitirBroadcast: false);
        }

        $porAutor = [];
        if ($mensajeIds->isNotEmpty()) {
            $mensajes = Mensaje::query()
                ->with(['user', 'lecturas', 'conversacion.participantes'])
                ->whereIn('id', $mensajeIds)
                ->get();

            foreach ($mensajes as $mensaje) {
                $autor = $mensaje->user;
                if (! $autor) {
                    continue;
                }

                $formateado = $this->formatearMensaje->ejecutar(
                    $mensaje,
                    $autor,
                    $mensaje->conversacion ?? $conversacion
                );
                $porAutor[$autor->id][] = [
                    'id' => $mensaje->id,
                    'estado_lectura' => $formateado['estado_lectura'],
                ];
            }
        }

        $this->notificarCursor->ejecutar(
            $conversacion->id,
            $user->id,
            $ahora->toIso8601String(),
            $porAutor
        );
    }
}
