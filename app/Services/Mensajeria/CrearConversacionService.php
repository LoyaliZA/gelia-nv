<?php

namespace App\Services\Mensajeria;

use App\Models\Conversacion;
use App\Models\ConversacionParticipante;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrearConversacionService
{
    public function ejecutar(User $creador, array $data): Conversacion
    {
        $tipo = $data['tipo'] ?? Conversacion::TIPO_DIRECTO;
        $participanteIds = collect($data['participante_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== $creador->id)
            ->unique()
            ->values();

        if ($tipo === Conversacion::TIPO_DIRECTO) {
            if ($participanteIds->count() !== 1) {
                throw ValidationException::withMessages([
                    'participante_ids' => 'Un chat directo requiere exactamente un destinatario.',
                ]);
            }

            $existente = $this->buscarDirectoExistente($creador->id, $participanteIds->first());
            if ($existente) {
                return $existente;
            }
        } else {
            if ($participanteIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'participante_ids' => 'Un grupo requiere al menos un participante.',
                ]);
            }
            if (empty($data['nombre'])) {
                throw ValidationException::withMessages([
                    'nombre' => 'El grupo requiere un nombre.',
                ]);
            }
        }

        return DB::transaction(function () use ($creador, $data, $tipo, $participanteIds) {
            $conversacion = Conversacion::create([
                'tipo' => $tipo,
                'nombre' => $tipo === Conversacion::TIPO_GRUPO ? $data['nombre'] : null,
                'foto' => $data['foto'] ?? null,
                'creado_por' => $creador->id,
            ]);

            $this->agregarParticipante($conversacion->id, $creador->id, ConversacionParticipante::ROL_ADMIN);

            foreach ($participanteIds as $userId) {
                $rol = $tipo === Conversacion::TIPO_GRUPO
                    ? ConversacionParticipante::ROL_MIEMBRO
                    : ConversacionParticipante::ROL_MIEMBRO;
                $this->agregarParticipante($conversacion->id, $userId, $rol);
            }

            return $conversacion->load(['participantes.user:id,name,foto_perfil']);
        });
    }

    private function buscarDirectoExistente(int $userA, int $userB): ?Conversacion
    {
        return Conversacion::query()
            ->where('tipo', Conversacion::TIPO_DIRECTO)
            ->whereHas('participantes', fn ($q) => $q->where('user_id', $userA))
            ->whereHas('participantes', fn ($q) => $q->where('user_id', $userB))
            ->withCount('participantes')
            ->get()
            ->first(fn ($c) => $c->participantes_count === 2);
    }

    private function agregarParticipante(int $conversacionId, int $userId, string $rol): void
    {
        ConversacionParticipante::firstOrCreate(
            ['conversacion_id' => $conversacionId, 'user_id' => $userId],
            ['rol' => $rol]
        );
    }
}
