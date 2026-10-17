<?php

namespace App\Services\GeliaAi;

use App\Models\GeliaAiConversacion;
use App\Models\GeliaAiMensaje;
use App\Models\User;

class GeliaAiConversacionService
{
    /** @return list<array{id: int, titulo: string|null, temporal: bool, updated_at: string|null}> */
    public function listar(User $user): array
    {
        return GeliaAiConversacion::query()
            ->where('user_id', $user->id)
            ->where('temporal', false)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'titulo', 'temporal', 'updated_at'])
            ->map(fn (GeliaAiConversacion $c) => [
                'id' => $c->id,
                'titulo' => $c->titulo ?: 'Chat sin título',
                'temporal' => (bool) $c->temporal,
                'updated_at' => $c->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function crearTemporal(User $user): GeliaAiConversacion
    {
        return GeliaAiConversacion::create([
            'user_id' => $user->id,
            'titulo' => null,
            'temporal' => true,
        ]);
    }

    public function obtenerDeUsuario(User $user, int $id): GeliaAiConversacion
    {
        return GeliaAiConversacion::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    public function contextoDe(User $user, int $conversacionId): array
    {
        $conv = GeliaAiConversacion::query()->where('user_id', $user->id)->whereKey($conversacionId)->first();

        return $conv && is_array($conv->contexto) ? $conv->contexto : [];
    }

    /** @return list<array{role: string, content: string}> */
    public function mensajesDe(GeliaAiConversacion $conversacion): array
    {
        return $conversacion->mensajes()
            ->get(['role', 'content'])
            ->map(fn (GeliaAiMensaje $m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])
            ->values()
            ->all();
    }

    public function eliminar(User $user, int $id): void
    {
        $this->obtenerDeUsuario($user, $id)->delete();
    }

    /**
     * Últimos mensajes de una conversación propia (el presupuesto final lo aplica el chat).
     *
     * @return array{historial: list<array{role: string, content: string}>, contexto: array<string, mixed>}
     */
    public function cargarParaChat(User $user, ?int $conversacionId): array
    {
        if (! $conversacionId) {
            return ['historial' => [], 'contexto' => []];
        }

        $conv = $this->obtenerDeUsuario($user, $conversacionId);
        $limite = max(0, (int) config('gelia_ai.history_max_messages', 24));
        $historial = $conv->mensajes()
            ->reorder('id', 'desc')
            ->limit($limite)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn (GeliaAiMensaje $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();

        return ['historial' => $historial, 'contexto' => is_array($conv->contexto) ? $conv->contexto : []];
    }

    /**
     * Agrega el resultado real de una acción confirmada para que la conversación pueda continuar con ese hecho.
     *
     * @param  array<string, mixed>  $contexto
     */
    public function registrarEventoAccion(User $user, int $conversacionId, string $texto, array $contexto): void
    {
        $conv = GeliaAiConversacion::query()->where('user_id', $user->id)->whereKey($conversacionId)->first();
        if (! $conv) {
            return;
        }

        GeliaAiMensaje::create([
            'conversacion_id' => $conv->id,
            'role' => 'assistant',
            'content' => $texto,
            'created_at' => now(),
        ]);
        $conv->contexto = $contexto;
        $conv->save();
    }

    /**
     * Crea o reutiliza conversación, persiste turno user+assistant y marca no temporal.
     *
     * @param  array<string, mixed>|null  $contexto
     * @return array{conversacion_id: int, titulo: string|null}
     */
    public function persistirTurno(User $user, ?int $conversacionId, string $mensajeUsuario, string $respuesta, ?array $contexto = null): array
    {
        $conversacion = null;
        if ($conversacionId) {
            $conversacion = GeliaAiConversacion::query()
                ->where('user_id', $user->id)
                ->whereKey($conversacionId)
                ->first();
        }

        if (! $conversacion) {
            $conversacion = $this->crearTemporal($user);
        }

        if (! $conversacion->titulo) {
            $conversacion->titulo = mb_substr(trim($mensajeUsuario), 0, 80);
        }

        $conversacion->temporal = false;
        if ($contexto !== null) {
            $conversacion->contexto = $contexto;
        }
        $conversacion->save();

        $now = now();
        GeliaAiMensaje::create([
            'conversacion_id' => $conversacion->id,
            'role' => 'user',
            'content' => $mensajeUsuario,
            'created_at' => $now,
        ]);
        GeliaAiMensaje::create([
            'conversacion_id' => $conversacion->id,
            'role' => 'assistant',
            'content' => $respuesta,
            'created_at' => $now,
        ]);

        $conversacion->touch();

        return [
            'conversacion_id' => $conversacion->id,
            'titulo' => $conversacion->titulo,
        ];
    }
}
