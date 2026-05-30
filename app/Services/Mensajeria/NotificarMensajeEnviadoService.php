<?php

namespace App\Services\Mensajeria;

use App\Events\MensajeEnviado;
use App\Events\MensajeRecibidoUsuario;
use App\Models\Conversacion;
use App\Models\ConversacionParticipante;
use App\Models\User;

class NotificarMensajeEnviadoService
{
    public function ejecutar(Conversacion $conversacion, User $remitente, array $mensajeFormateado): void
    {
        broadcast(new MensajeEnviado($conversacion->id, $mensajeFormateado))->toOthers();

        $participantes = ConversacionParticipante::query()
            ->where('conversacion_id', $conversacion->id)
            ->where('user_id', '!=', $remitente->id)
            ->pluck('user_id');

        foreach ($participantes as $userId) {
            broadcast(new MensajeRecibidoUsuario($userId, $mensajeFormateado))->toOthers();
        }
    }
}
