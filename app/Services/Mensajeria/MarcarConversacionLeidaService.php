<?php

namespace App\Services\Mensajeria;

use App\Models\Conversacion;
use App\Models\ConversacionParticipante;
use App\Models\Mensaje;
use App\Models\MensajeLectura;
use App\Models\User;
use Illuminate\Support\Collection;

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

        $mensajesNoLeidos = Mensaje::query()
            ->where('conversacion_id', $conversacion->id)
            ->where('user_id', '!=', $user->id)
            ->whereDoesntHave('lecturas', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        if ($mensajesNoLeidos->isEmpty()) {
            return;
        }

        $this->registrarLecturasMasivas($mensajesNoLeidos, $user);
    }

    private function registrarLecturasMasivas(Collection $mensajeIds, User $user): void
    {
        $ahora = now();

        foreach ($mensajeIds as $mensajeId) {
            MensajeLectura::firstOrCreate(
                ['mensaje_id' => $mensajeId, 'user_id' => $user->id],
                ['leido_at' => $ahora]
            );
        }

        $ultimoId = $mensajeIds->last();
        if ($ultimoId) {
            $this->registrarLectura->ejecutar($ultimoId, $user, emitirBroadcast: true);
        }
    }
}
