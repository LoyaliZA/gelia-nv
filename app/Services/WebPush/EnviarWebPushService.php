<?php

namespace App\Services\WebPush;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class EnviarWebPushService
{
    public function estaConfigurado(): bool
    {
        if (!config('webpush.enabled', true)) {
            return false;
        }

        return (bool) config('webpush.vapid.public_key')
            && (bool) config('webpush.vapid.private_key');
    }

    public function enviarAUsuario(User|int $user, array $payload): int
    {
        $userId = $user instanceof User ? $user->id : (int) $user;

        $subs = PushSubscription::where('user_id', $userId)->get();

        return $this->enviarASuscripciones($subs, $payload);
    }

    public function enviarAUsuarios(Collection|array $userIds, array $payload): int
    {
        $ids = collect($userIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return 0;
        }

        $subs = PushSubscription::whereIn('user_id', $ids)->get();

        return $this->enviarASuscripciones($subs, $payload);
    }

    public function enviarASuscripciones(Collection $suscripciones, array $payload): int
    {
        if (!$this->estaConfigurado() || $suscripciones->isEmpty()) {
            return 0;
        }

        $webPush = $this->cliente();
        $json = json_encode($this->normalizarPayload($payload), JSON_UNESCAPED_UNICODE);
        $enviados = 0;

        foreach ($suscripciones as $sub) {
            try {
                $encoding = $sub->content_encoding ?: 'aes128gcm';

                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->public_key,
                    'authToken' => $sub->auth_token,
                    'contentEncoding' => $encoding,
                ]);

                $report = $webPush->sendOneNotification($subscription, $json);

                if ($report->isSuccess()) {
                    $enviados++;
                    continue;
                }

                $status = $report->getResponse()?->getStatusCode();
                if (in_array($status, [404, 410], true)) {
                    $sub->delete();
                }

                Log::warning('[WebPush] Fallo al enviar', [
                    'endpoint' => $sub->endpoint,
                    'reason' => $report->getReason(),
                    'status' => $status,
                    'encoding' => $encoding,
                ]);
            } catch (\Throwable $e) {
                Log::error('[WebPush] Error de envío', [
                    'endpoint' => $sub->endpoint,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $enviados;
    }

    public function registrarSuscripcion(User $user, array $datos): PushSubscription
    {
        return PushSubscription::updateOrCreate(
            ['endpoint' => $datos['endpoint']],
            [
                'user_id' => $user->id,
                'public_key' => $datos['keys']['p256dh'] ?? $datos['public_key'] ?? '',
                'auth_token' => $datos['keys']['auth'] ?? $datos['auth_token'] ?? '',
                'content_encoding' => $datos['content_encoding'] ?? 'aesgcm',
                'user_agent' => $datos['user_agent'] ?? null,
            ]
        );
    }

    public function eliminarSuscripcion(User $user, ?string $endpoint = null): int
    {
        $query = PushSubscription::where('user_id', $user->id);

        if ($endpoint) {
            $query->where('endpoint', $endpoint);
        }

        return $query->delete();
    }

    private function cliente(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => config('webpush.vapid.subject'),
                'publicKey' => config('webpush.vapid.public_key'),
                'privateKey' => config('webpush.vapid.private_key'),
            ],
        ]);
    }

    private function normalizarPayload(array $payload): array
    {
        $defaults = config('webpush.defaults', []);

        return [
            'title' => $payload['title'] ?? 'GELIA ERP',
            'body' => $payload['body'] ?? '',
            'icon' => $payload['icon'] ?? $defaults['icon'] ?? '/favicon.svg',
            'badge' => $payload['badge'] ?? $defaults['badge'] ?? '/favicon.svg',
            'url' => $payload['url'] ?? url('/dashboard'),
            'tag' => $payload['tag'] ?? null,
            'tipo' => $payload['tipo'] ?? null,
            'data' => $payload['data'] ?? [],
        ];
    }
}
