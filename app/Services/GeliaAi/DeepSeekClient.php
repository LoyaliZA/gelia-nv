<?php

namespace App\Services\GeliaAi;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeepSeekClient
{
    /** Nombres heredados que no aceptan el parámetro thinking. */
    private const MODELOS_HEREDADOS = ['deepseek-chat', 'deepseek-reasoner'];

    public function estaConfigurado(): bool
    {
        return $this->apiToken() !== '' && $this->baseUrl() !== '';
    }

    public function apiToken(): string
    {
        return trim((string) (config('deepseek.api_token') ?: config('services.deepseek.api_token') ?: ''));
    }

    public function baseUrl(): string
    {
        $url = trim((string) (config('deepseek.base_url') ?: config('services.deepseek.base_url') ?: 'https://api.deepseek.com'));

        return rtrim($url, '/');
    }

    public function model(): string
    {
        $model = trim((string) (config('gelia_ai.model') ?: 'deepseek-chat'));

        return $model !== '' ? $model : 'deepseek-chat';
    }

    public function thinkingHabilitado(): bool
    {
        return ! in_array($this->model(), self::MODELOS_HEREDADOS, true)
            && strtolower((string) config('gelia_ai.thinking', 'disabled')) === 'enabled';
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>|null  $tools
     * @param  string|array<string, mixed>|null  $toolChoice  'auto'|'none'
     * @return array<string, mixed>
     */
    public function chat(array $messages, ?array $tools = null, int $maxTokens = 600, string|array|null $toolChoice = null, ?int $timeoutSeconds = null): array
    {
        if (! $this->estaConfigurado()) {
            throw new RuntimeException('DeepSeek no está configurado (api_token / base_url).');
        }

        $payload = [
            'model' => $this->model(),
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ];

        if (! in_array($this->model(), self::MODELOS_HEREDADOS, true)) {
            $payload['thinking'] = ['type' => $this->thinkingHabilitado() ? 'enabled' : 'disabled'];
        }
        if (! $this->thinkingHabilitado()) {
            $payload['temperature'] = 0.3;
        }

        if ($tools !== null && $tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = $toolChoice ?? 'auto';
        }

        $timeout = max(5, $timeoutSeconds ?? (int) config('gelia_ai.timeout_seconds', 45));

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withToken($this->apiToken())
                ->acceptJson()
                ->timeout($timeout)
                ->post('/chat/completions', $payload);
        } catch (ConnectionException $e) {
            throw new DeepSeekException('El servicio de IA no respondió a tiempo. Intenta de nuevo en unos momentos.', 504, $e);
        }

        if ($response->failed()) {
            $body = $response->json();
            $detalle = is_array($body) ? (string) ($body['error']['message'] ?? '') : '';
            report(new RuntimeException("DeepSeek HTTP {$response->status()}: {$detalle}"));

            throw new DeepSeekException($this->mensajeError($response->status()), $response->status() === 429 ? 429 : 502);
        }

        $json = $response->json();
        if (! is_array($json) || ! is_array($json['choices'][0]['message'] ?? null)) {
            throw new DeepSeekException('El servicio de IA devolvió una respuesta inválida. Intenta de nuevo.');
        }

        return $json;
    }

    private function mensajeError(int $status): string
    {
        return match (true) {
            $status === 401 => 'El servicio de IA rechazó la credencial configurada. Un admin debe revisar deepseek.api_token.',
            $status === 402 => 'La cuenta del servicio de IA no tiene saldo suficiente. Un admin debe revisarla.',
            $status === 400, $status === 422 => 'El servicio de IA rechazó la solicitud (modelo o parámetros no válidos). Un admin debe revisar la configuración de Gelia AI.',
            $status === 429 => 'El servicio de IA está saturado. Espera unos segundos e intenta de nuevo.',
            $status >= 500 => 'El servicio de IA no está disponible por ahora. Intenta de nuevo en unos momentos.',
            default => 'No se pudo consultar el servicio de IA.',
        };
    }
}
