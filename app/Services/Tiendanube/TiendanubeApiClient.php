<?php

namespace App\Services\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubeApiContractException;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
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
        return $this->decodeObject($this->client()->get('/store'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCategoriesPage(int $page = 1, ?int $perPage = null): array
    {
        $perPage ??= (int) config('tiendanube.per_page', 50);

        return $this->decodeCollection($this->client()->get('/categories', [
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

        return $this->decodeCollection($this->client()->get('/products', [
            'page' => $page,
            'per_page' => $perPage,
        ]));
    }

    public function getProduct(int $id): array
    {
        return $this->decodeObject($this->client()->get("/products/{$id}"));
    }

    public function getCategory(int $id): array
    {
        return $this->decodeObject($this->client()->get("/categories/{$id}"));
    }

    /**
     * @return array{estado: 'existe'|'ausente'|'indeterminado', recurso: ?array<string, mixed>, detalle: ?string}
     */
    public function consultarProducto(int $id): array
    {
        return $this->consultarRecurso("/products/{$id}");
    }

    /**
     * @return array{estado: 'existe'|'ausente'|'indeterminado', recurso: ?array<string, mixed>, detalle: ?string}
     */
    public function consultarCategoria(int $id): array
    {
        return $this->consultarRecurso("/categories/{$id}");
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
        return $this->decodeObject($this->client()->get("/webhooks/{$id}"));
    }

    /**
     * @return array<string, mixed>
     */
    public function createWebhook(string $event, string $url): array
    {
        return $this->decodeObject($this->client()->post('/webhooks', [
            'event' => $event,
            'url' => $url,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function updateWebhook(int $id, string $event, string $url): array
    {
        return $this->decodeObject($this->client()->put("/webhooks/{$id}", [
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
        return $this->decodeObject($this->client()->post('/products', $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateProduct(int $id, array $payload): array
    {
        return $this->decodeObject($this->client()->put("/products/{$id}", $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateVariant(int $productId, int $variantId, array $payload): array
    {
        return $this->decodeObject($this->client()->put("/products/{$productId}/variants/{$variantId}", $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createProductImage(int $productId, array $payload): array
    {
        return $this->decodeObject($this->client()->post("/products/{$productId}/images", $payload));
    }

    public function deleteProductImage(int $productId, int $imageId): void
    {
        $this->deleteProductImageResult($productId, $imageId);
    }

    /**
     * @return 'ok'|'no_encontrado'
     */
    public function deleteProductImageResult(int $productId, int $imageId): string
    {
        try {
            $response = $this->client()->delete("/products/{$productId}/images/{$imageId}");
        } catch (RequestException $e) {
            $response = $e->response;
            if ($response && $response->status() === 404) {
                return 'no_encontrado';
            }
            throw $this->excepcionDesdeRespuesta($response, $e);
        }

        if ($response->status() === 404) {
            return 'no_encontrado';
        }
        $this->assertOk($response);

        return 'ok';
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

    /**
     * @return array{estado: 'existe'|'ausente'|'indeterminado', recurso: ?array<string, mixed>, detalle: ?string}
     */
    private function consultarRecurso(string $path): array
    {
        try {
            $response = $this->client()->get($path);
        } catch (RequestException $e) {
            $response = $e->response;
            if (! $response) {
                return ['estado' => 'indeterminado', 'recurso' => null, 'detalle' => $e->getMessage()];
            }
        } catch (\Throwable $e) {
            return ['estado' => 'indeterminado', 'recurso' => null, 'detalle' => $e->getMessage()];
        }

        if ($response->status() === 404) {
            return ['estado' => 'ausente', 'recurso' => null, 'detalle' => null];
        }

        if (! $response->successful()) {
            return ['estado' => 'indeterminado', 'recurso' => null, 'detalle' => "HTTP {$response->status()}"];
        }

        try {
            $recurso = $this->decodeObject($response);
        } catch (TiendanubeApiContractException $e) {
            return ['estado' => 'indeterminado', 'recurso' => null, 'detalle' => $e->getMessage()];
        }

        if (! isset($recurso['id']) || ! is_numeric($recurso['id']) || (int) $recurso['id'] < 1) {
            return ['estado' => 'indeterminado', 'recurso' => null, 'detalle' => 'Objeto sin ID válido.'];
        }

        return ['estado' => 'existe', 'recurso' => $recurso, 'detalle' => null];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(Response $response): array
    {
        $this->assertOk($response);

        try {
            $json = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new TiendanubeApiContractException('Respuesta JSON inválida de Tiendanube.', 0, $e);
        }

        if (! is_array($json)) {
            throw new TiendanubeApiContractException('Se esperaba un documento JSON de tipo objeto o lista.');
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeObject(Response $response): array
    {
        $data = $this->decodeJson($response);

        if ($data !== [] && array_is_list($data)) {
            throw new TiendanubeApiContractException('Se esperaba un objeto JSON.');
        }

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeCollection(Response $response): array
    {
        $data = $this->decodeList($response);

        foreach ($data as $indice => $item) {
            if (! is_array($item) || ! isset($item['id']) || ! is_numeric($item['id']) || (int) $item['id'] < 1) {
                throw new TiendanubeApiContractException("Recurso de catálogo sin ID válido (índice {$indice}).");
            }
        }

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeList(Response $response): array
    {
        $data = $this->decodeJson($response);

        if (! array_is_list($data)) {
            throw new TiendanubeApiContractException('Se esperaba una lista JSON.');
        }

        return $data;
    }

    private function excepcionDesdeRespuesta(?Response $response, \Throwable $prev): RuntimeException
    {
        if (! $response) {
            return new RuntimeException('Tiendanube API: '.$prev->getMessage(), 0, $prev);
        }

        $json = $response->json();
        $detalle = is_array($json) ? $this->formatearErrorApi($json) : (string) $response->body();

        return new RuntimeException("Tiendanube API HTTP {$response->status()}: {$detalle}", 0, $prev);
    }

    private function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $json = $response->json();
        $detalle = is_array($json) ? $this->formatearErrorApi($json) : (string) $response->body();

        throw new RuntimeException("Tiendanube API HTTP {$response->status()}: {$detalle}");
    }

    /** @param array<string, mixed> $json */
    private function formatearErrorApi(array $json): string
    {
        $partes = [];

        foreach (['message', 'description', 'code'] as $clave) {
            if (! empty($json[$clave]) && is_scalar($json[$clave])) {
                $partes[] = (string) $json[$clave];
            }
        }

        foreach ($json as $campo => $valor) {
            if (in_array($campo, ['message', 'description', 'code'], true)) {
                continue;
            }
            if (is_array($valor)) {
                $msgs = array_values(array_filter($valor, fn ($v) => is_scalar($v)));
                if ($msgs !== []) {
                    $partes[] = $campo.': '.implode(' | ', array_map('strval', $msgs));
                }
            }
        }

        return $partes !== [] ? implode(' — ', $partes) : json_encode($json, JSON_UNESCAPED_UNICODE);
    }
}
