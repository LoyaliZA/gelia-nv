<?php

namespace App\Services\Tiendanube;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TiendanubeApiClient
{
    public function __construct(
        private ?TiendanubeConfiguracion $config = null
    ) {}

    public function config(): TiendanubeConfiguracion
    {
        return $this->config ??= TiendanubeConfiguracion::obtener();
    }

    public function client(): PendingRequest
    {
        $config = $this->config();
        $token = $config->accessTokenDecrypted();
        $storeId = $config->store_id;
        $appId = $config->appIdEfectivo();

        if (! $token || ! $storeId) {
            throw new RuntimeException('Credenciales de Tiendanube no configuradas.');
        }

        $apiBase = (string) (config('tiendanube.api_base') ?: 'https://api.tiendanube.com/v1');
        $base = rtrim($apiBase, '/').'/'.$storeId;
        $ua = trim((string) (config('tiendanube.user_agent') ?: 'Gelianv')).' ('.$appId.')';

        return Http::baseUrl($base)
            ->withHeaders([
                'Authentication' => 'bearer '.$token,
                'User-Agent' => $ua,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout(60)
            ->retry(3, 1500, function ($exception, $request) {
                if (! method_exists($exception, 'response') || ! $exception->response) {
                    return false;
                }

                return $exception->response->status() === 429;
            });
    }

    public function getStore(): array
    {
        return $this->decode($this->client()->get('/store'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCategoriesPage(int $page = 1, ?int $perPage = null): array
    {
        $perPage ??= (int) config('tiendanube.per_page', 50);

        return $this->decodeList($this->client()->get('/categories', [
            'page' => $page,
            'per_page' => $perPage,
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getProductsPage(int $page = 1, ?int $perPage = null): array
    {
        $perPage ??= (int) config('tiendanube.per_page', 50);

        return $this->decodeList($this->client()->get('/products', [
            'page' => $page,
            'per_page' => $perPage,
        ]));
    }

    public function getProduct(int $id): array
    {
        return $this->decode($this->client()->get("/products/{$id}"));
    }

    public function getCategory(int $id): array
    {
        return $this->decode($this->client()->get("/categories/{$id}"));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function listWebhooks(array $query = []): array
    {
        return $this->decodeList($this->client()->get('/webhooks', $query));
    }

    public function getWebhook(int $id): array
    {
        return $this->decode($this->client()->get("/webhooks/{$id}"));
    }

    /**
     * @return array<string, mixed>
     */
    public function createWebhook(string $event, string $url): array
    {
        return $this->decode($this->client()->post('/webhooks', [
            'event' => $event,
            'url' => $url,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function updateWebhook(int $id, string $event, string $url): array
    {
        return $this->decode($this->client()->put("/webhooks/{$id}", [
            'event' => $event,
            'url' => $url,
        ]));
    }

    public function deleteWebhook(int $id): void
    {
        $this->assertOk($this->client()->delete("/webhooks/{$id}"));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createProduct(array $payload): array
    {
        return $this->decode($this->client()->post('/products', $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateProduct(int $id, array $payload): array
    {
        return $this->decode($this->client()->put("/products/{$id}", $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateVariant(int $productId, int $variantId, array $payload): array
    {
        return $this->decode($this->client()->put("/products/{$productId}/variants/{$variantId}", $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createProductImage(int $productId, array $payload): array
    {
        return $this->decode($this->client()->post("/products/{$productId}/images", $payload));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paginateAll(callable $pageFetcher): array
    {
        $all = [];
        $page = 1;

        do {
            $chunk = $pageFetcher($page);
            $all = array_merge($all, $chunk);
            $page++;
        } while (count($chunk) >= (int) config('tiendanube.per_page', 50));

        return $all;
    }

    private function decode(Response $response): array
    {
        $this->assertOk($response);
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeList(Response $response): array
    {
        $data = $this->decode($response);

        return array_is_list($data) ? $data : [];
    }

    private function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $body = $response->json('message') ?? $response->body();

        throw new RuntimeException("Tiendanube API HTTP {$response->status()}: {$body}");
    }
}
