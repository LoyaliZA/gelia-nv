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
            $this->registrarLectura->ejecutar($mensajeId, $user, emitirBroadcast: true);
        }
    }
}
