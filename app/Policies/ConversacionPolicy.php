<?php

namespace App\Policies;

use App\Models\Conversacion;
use App\Models\ConversacionParticipante;
use App\Models\User;

class ConversacionPolicy
{
    public function view(User $user, Conversacion $conversacion): bool
    {
        return $this->esParticipante($user, $conversacion);
    }

    public function sendMessage(User $user, Conversacion $conversacion): bool
    {
        return $this->esParticipante($user, $conversacion);
    }

    public function manageGroup(User $user, Conversacion $conversacion): bool
    {
        if (!$conversacion->esGrupo()) {
            return false;
        }

        return ConversacionParticipante::where('conversacion_id', $conversacion->id)
            ->where('user_id', $user->id)
            ->where('rol', ConversacionParticipante::ROL_ADMIN)
            ->exists();
    }

    private function esParticipante(User $user, Conversacion $conversacion): bool
    {
        return ConversacionParticipante::where('conversacion_id', $conversacion->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
