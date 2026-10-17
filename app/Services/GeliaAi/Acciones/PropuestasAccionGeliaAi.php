<?php

namespace App\Services\GeliaAi\Acciones;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Propuestas validadas en servidor y pendientes de confirmación.
 * Confirmar ejecuta exactamente el payload guardado (uso único), no lo que reenvíe el cliente.
 */
class PropuestasAccionGeliaAi
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $advertencias
     * @return array<string, mixed>
     */
    public function registrar(User $user, string $accion, array $payload, string $resumen, array $advertencias = []): array
    {
        $propuesta = [
            'id' => (string) Str::uuid(),
            'user_id' => (int) $user->id,
            'accion' => $accion,
            'payload' => $payload,
            'resumen' => $resumen,
            'advertencias' => $advertencias,
            'creada_en' => now()->toIso8601String(),
        ];

        Cache::put($this->key($user, $propuesta['id']), $propuesta, now()->addMinutes((int) config('gelia_ai.propuesta_ttl_minutes', 30)));

        return $propuesta;
    }

    /** @return array<string, mixed>|null */
    public function consumir(User $user, string $id): ?array
    {
        $propuesta = Cache::pull($this->key($user, $id));

        return is_array($propuesta) && (int) ($propuesta['user_id'] ?? 0) === (int) $user->id ? $propuesta : null;
    }

    private function key(User $user, string $id): string
    {
        return "gelia_ai_propuesta:{$user->id}:{$id}";
    }
}
