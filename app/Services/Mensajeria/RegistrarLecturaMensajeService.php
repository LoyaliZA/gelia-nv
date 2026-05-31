<?php

namespace App\Services\Mensajeria;

use App\Events\MensajeLeido;
use App\Events\MensajeLeidoUsuario;
use App\Models\ConversacionParticipante;
use App\Models\Mensaje;
use App\Models\MensajeLectura;
use App\Models\User;

class RegistrarLecturaMensajeService
{
    public function __construct(
        private FormatearMensajeService $formatearMensaje,
    ) {}

    public function ejecutar(int $mensajeId, User $user, bool $emitirBroadcast = true): ?array
    {
        $mensaje = Mensaje::with(['conversacion.participantes', 'user', 'adjuntos', 'lecturas'])->find($mensajeId);

        if (!$mensaje || $mensaje->user_id === $user->id) {
            return null;
        }

        $esParticipante = ConversacionParticipante::where('conversacion_id', $mensaje->conversacion_id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$esParticipante) {
            return null;
        }

        $autor = $mensaje->user ?? $mensaje->loadMissing('user')->user;
        $estadoLecturaAutorAntes = null;
        if ($autor) {
            $estadoLecturaAutorAntes = $this->formatearMensaje
                ->ejecutar($mensaje, $autor, $mensaje->conversacion)['estado_lectura'];
        }

        $lectura = MensajeLectura::firstOrCreate(
            ['mensaje_id' => $mensajeId, 'user_id' => $user->id],
            ['leido_at' => now()]
        );

        $mensaje->load('lecturas');

        $formateado = $this->formatearMensaje->ejecutar($mensaje, $user, $mensaje->conversacion);

        if ($emitirBroadcast && $autor) {
            $paraEmisor = $this->formatearMensaje->ejecutar($mensaje, $autor, $mensaje->conversacion);
            $estadoCambio = $lectura->wasRecentlyCreated
                || $estadoLecturaAutorAntes !== $paraEmisor['estado_lectura'];

            if ($estadoCambio) {
                broadcast(new MensajeLeido($mensaje->conversacion_id, $paraEmisor))->toOthers();
                broadcast(new MensajeLeidoUsuario($autor->id, $paraEmisor));
            }
        }

        return $formateado;
    }
}
