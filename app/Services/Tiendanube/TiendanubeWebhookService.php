<?php

namespace App\Services\Tiendanube;

use InvalidArgumentException;
use RuntimeException;

class TiendanubeWebhookService
{
    public function __construct(
        private TiendanubeApiClient $api
    ) {}

    public function webhookUrl(): string
    {
        $configured = trim((string) config('tiendanube.webhook_url'));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return rtrim((string) config('app.url'), '/').'/webhooks/tiendanube';
    }

    /**
     * @return list<string>
     */
    public function eventosRecomendados(): array
    {
        $events = config('tiendanube.webhook_events', []);

        return is_array($events) ? array_values(array_filter($events, 'is_string')) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listar(): array
    {
        return $this->api->listWebhooks();
    }

    /**
     * @return array<string, mixed>
     */
    public function crear(string $event, ?string $url = null): array
    {
        $url = $url ?: $this->webhookUrl();
        $this->assertHttpsUrl($url);

        return $this->api->createWebhook($event, $url);
    }

    /**
     * @return array<string, mixed>
     */
    public function actualizar(int $id, string $event, string $url): array
    {
        $this->assertHttpsUrl($url);

        return $this->api->updateWebhook($id, $event, $url);
    }

    public function eliminar(int $id): void
    {
        $this->api->deleteWebhook($id);
    }

    /**
     * @return array{creados: list<array<string, mixed>>, ya_existentes: list<string>, errores: list<array{event: string, message: string}>}
     */
    public function aplicarRecomendados(?string $url = null): array
    {
        $url = $url ?: $this->webhookUrl();
        $this->assertHttpsUrl($url);

        $existentes = $this->listar();
        $creados = [];
        $yaExistentes = [];
        $errores = [];

        foreach ($this->eventosRecomendados() as $event) {
            $yaHay = collect($existentes)->contains(
                fn (array $wh) => ($wh['event'] ?? null) === $event && ($wh['url'] ?? null) === $url
            );

            if ($yaHay) {
                $yaExistentes[] = $event;

                continue;
            }

            try {
                $creados[] = $this->api->createWebhook($event, $url);
            } catch (\Throwable $e) {
                $errores[] = [
                    'event' => $event,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'creados' => $creados,
            'ya_existentes' => $yaExistentes,
            'errores' => $errores,
        ];
    }

    public function assertHttpsUrl(string $url): void
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('URL de webhook inválida.');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https') {
            throw new InvalidArgumentException('La URL del webhook debe usar HTTPS.');
        }

        if ($host === '' || $host === 'localhost' || str_starts_with($host, '127.') || $host === '::1') {
            throw new InvalidArgumentException('La URL del webhook no puede ser localhost.');
        }

        if (str_contains($host, 'tiendanube.com') || str_contains($host, 'nuvemshop.com')) {
            throw new InvalidArgumentException('La URL del webhook no puede usar un dominio de Tiendanube/Nuvemshop.');
        }
    }

    public function requireAppSecret(): string
    {
        $secret = (string) config('tiendanube.app_secret');
        if ($secret === '') {
            throw new RuntimeException('TIENDANUBE_APP_SECRET no configurado.');
        }

        return $secret;
    }
}
