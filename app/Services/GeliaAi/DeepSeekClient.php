<?php

namespace App\Services\GeliaAi;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeepSeekClient
{
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

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>|null  $tools
     * @param  string|array<string, mixed>|null  $toolChoice  'auto'|'none'|['type'=>'function','function'=>['name'=>...]]
     * @return array<string, mixed>
     */
    public function chat(array $messages, ?array $tools = null, int $maxTokens = 600, string|array|null $toolChoice = null): array
    {
        if (! $this->estaConfigurado()) {
            throw new RuntimeException('DeepSeek no está configurado (api_token / base_url).');
        }

        $payload = [
            'model' => $this->model(),
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => 0.3,
        ];

        if ($tools !== null && $tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = $toolChoice ?? 'auto';
        }

        $response = Http::baseUrl($this->baseUrl())
            ->withToken($this->apiToken())
            ->acceptJson()
            ->timeout((int) config('gelia_ai.timeout_seconds', 45))
            ->post('/chat/completions', $payload);

        try {
            $response->throw();
        } catch (RequestException $e) {
            $body = $e->response?->json();
            $msg = is_array($body)
                ? (string) ($body['error']['message'] ?? $e->getMessage())
                : $e->getMessage();
            throw new RuntimeException('DeepSeek error: '.$msg, previous: $e);
        }

        /** @var array<string, mixed> */
        return $response->json();
    }
}
