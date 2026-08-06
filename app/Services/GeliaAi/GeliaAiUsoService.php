<?php

namespace App\Services\GeliaAi;

use App\Models\GeliaAiUso;
use App\Models\User;
use Throwable;

class GeliaAiUsoService
{
    /**
     * @param  array{reply?: string, usage?: array<string, mixed>|null}  $result
     */
    public function registrar(
        User $user,
        ?int $conversacionId,
        string $mensajeUsuario,
        array $result,
        bool $conArchivos = false,
    ): void {
        try {
            $usage = is_array($result['usage'] ?? null) ? $result['usage'] : [];
            $acc = is_array($usage['gelia_acc'] ?? null) ? $usage['gelia_acc'] : [];
            $reply = (string) ($result['reply'] ?? '');

            GeliaAiUso::create([
                'user_id' => $user->id,
                'conversacion_id' => $conversacionId,
                'prompt_tokens' => (int) ($acc['prompt'] ?? $usage['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($acc['completion'] ?? $usage['completion_tokens'] ?? 0),
                'total_tokens' => (int) ($acc['total'] ?? $usage['total_tokens'] ?? 0),
                'rounds' => (int) ($acc['rounds'] ?? 0),
                'mode' => isset($usage['gelia_mode']) ? (string) $usage['gelia_mode'] : null,
                'modelo' => trim((string) (config('gelia_ai.model') ?: 'deepseek-chat')) ?: null,
                'mensaje_chars' => mb_strlen($mensajeUsuario),
                'reply_chars' => mb_strlen($reply),
                'con_archivos' => $conArchivos,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
