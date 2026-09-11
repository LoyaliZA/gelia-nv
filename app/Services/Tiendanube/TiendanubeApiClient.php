<?php

namespace App\Services\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubeApiException;
use App\Exceptions\Tiendanube\TiendanubeApiNotFoundException;
use App\Exceptions\Tiendanube\TiendanubeApiPaymentRequiredException;
use App\Exceptions\Tiendanube\TiendanubeApiRateLimitException;
use App\Exceptions\Tiendanube\TiendanubeApiRouteException;
use App\Exceptions\Tiendanube\TiendanubeApiValidationException;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TiendanubeApiClient
{
    public const ALLOWED_VERSIONS = ['v1', '2025-03'];

    public function __construct(
        private ?TiendanubeConfiguracion $config = null
    ) {}

    public function config(): TiendanubeConfiguracion
    {
        return $this->config ??= TiendanubeConfiguracion::obtener();
    }

    public function client(?string $method = 'GET', string $path = '/'): PendingRequest
    {
        $config = $this->config();
        $token = $config->accessTokenDecrypted();
        $storeId = $config->store_id;

        if (! $token || ! $storeId) {
            throw new RuntimeException('Credenciales de Tiendanube no configuradas.');
        }

        $method = strtoupper($method);
        $sleepMs = max(0, (int) config('tiendanube.retry_sleep_ms', 1500));

        return Http::baseUrl($this->resolveBaseUrl())
            ->withToken($token)
            ->withHeaders([
                'User-Agent' => $this->userAgent(),
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json',
            ])
            ->timeout(60)
            ->retry(
                3,
                function (int $attempt, mixed $exception) use ($sleepMs): int {
                    if ($exception instanceof RequestException) {
                        $reset = $exception->response?->header('x-rate-limit-reset');
                        if (is_numeric($reset)) {
                            return min(max((int) $reset, 0), 10_000);
                        }
                    }

                    return $sleepMs;
                },
                function (Throwable $exception) use ($method, $path): bool {
                    return $this->shouldRetry($exception, $method, $path);
                },
                throw: false,
            );
    }

    public function resolveBaseUrl(): string
    {
        $storeId = (string) $this->config()->store_id;
        $legacy = trim((string) config('tiendanube.api_base', ''));

        if ($legacy !== '') {
            $base = rtrim($legacy, '/');
            $this->assertValidApiBase($base);
            if (preg_match('#/'.preg_quote($storeId, '#').'$#', $base)) {
                return $base;
            }

            return $base.'/'.$storeId;
        }

        $host = rtrim((string) (config('tiendanube.api_host') ?: 'https://api.tiendanube.com'), '/');
        $version = $this->configuredVersion();

        return $host.'/'.$version.'/'.$storeId;
    }

    public function configuredVersion(): string
    {
        $legacy = trim((string) config('tiendanube.api_base', ''));
        if ($legacy !== '') {
            $path = (string) (parse_url($legacy, PHP_URL_PATH) ?: '');
            foreach (array_reverse(explode('/', $path)) as $seg) {
                if ($seg !== '' && in_array($seg, self::ALLOWED_VERSIONS, true)) {
                    return $seg;
                }
            }
        }

        $version = (string) (config('tiendanube.api_version') ?: 'v1');
        $this->assertAllowedVersion($version);

        return $version;
    }

    public function configuredHost(): string
    {
        $legacy = trim((string) config('tiendanube.api_base', ''));
        if ($legacy !== '') {
            $parts = parse_url($legacy);

            return strtolower((string) ($parts['host'] ?? ''));
        }

        $host = rtrim((string) (config('tiendanube.api_host') ?: 'https://api.tiendanube.com'), '/');

        return strtolower((string) (parse_url($host, PHP_URL_HOST) ?: ''));
    }

    public function userAgent(): string
    {
        $name = trim((string) (config('tiendanube.user_agent') ?: 'Gelianv'));
        $contact = trim((string) (config('tiendanube.user_agent_contact') ?: config('app.url') ?: ''));
        if ($contact === '') {
            $contact = 'app-'.$this->config()->appIdEfectivo();
        }

        return $name.' ('.$contact.')';
    }

    /**
     * @return array<string, mixed>
     */
    public function probeReadOnly(): array
    {
        $store = $this->getStore();
        $checks = [
            'store' => 'ok',
            'categories' => 'ok',
            'locations' => 'skipped',
        ];

        $this->getCategoriesPage(1, 1);

        try {
            $this->getLocations();
            $checks['locations'] = 'ok';
        } catch (TiendanubeApiException $e) {
            $checks['locations'] = match (true) {
                in_array($e->statusCode, [401, 403], true) => 'forbidden',
                $e->statusCode === 404 => 'unavailable',
                default => 'error',
            };
        }

        return [
            'store' => $store,
            'checks' => $checks,
            'api_version' => $this->configuredVersion(),
            'api_host' => $this->configuredHost(),
        ];
    }

    public function getStore(): array
    {
        return $this->decode($this->send('GET', '/store'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getLocations(): array
    {
        return $this->decodeList($this->send('GET', '/locations'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCategoriesPage(int $page = 1, ?int $perPage = null): array
    {
        $perPage ??= (int) config('tiendanube.per_page', 50);

        return $this->decodeList($this->send('GET', '/categories', [
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

        return $this->decodeList($this->send('GET', '/products', [
            'page' => $page,
            'per_page' => $perPage,
        ]));
    }

    public function getProduct(int $id): array
    {
        return $this->decode($this->send('GET', "/products/{$id}"));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findProductsBySku(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        return $this->decodeList($this->send('GET', '/products', [
            'sku' => $sku,
            'page' => 1,
            'per_page' => (int) config('tiendanube.per_page', 50),
        ]));
    }

    public function getCategory(int $id): array
    {
        return $this->decode($this->send('GET', "/categories/{$id}"));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function listWebhooks(array $query = []): array
    {
        return $this->decodeList($this->send('GET', '/webhooks', $query));
    }

    public function getWebhook(int $id): array
    {
        return $this->decode($this->send('GET', "/webhooks/{$id}"));
    }

    /**
     * @return array<string, mixed>
     */
    public function createWebhook(string $event, string $url): array
    {
        return $this->decode($this->send('POST', '/webhooks', [
            'event' => $event,
            'url' => $url,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function updateWebhook(int $id, string $event, string $url): array
    {
        return $this->decode($this->send('PUT', "/webhooks/{$id}", [
            'event' => $event,
            'url' => $url,
        ]));
    }

    public function deleteWebhook(int $id): void
    {
        $this->assertOk($this->send('DELETE', "/webhooks/{$id}"), 'DELETE', "/webhooks/{$id}");
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createProduct(array $payload): array
    {
        return $this->decode($this->send('POST', '/products', $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateProduct(int $id, array $payload): array
    {
        return $this->decode($this->send('PUT', "/products/{$id}", $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateVariant(int $productId, int $variantId, array $payload): array
    {
        return $this->decode($this->send('PUT', "/products/{$productId}/variants/{$variantId}", $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createProductImage(int $productId, array $payload): array
    {
        return $this->decode($this->send('POST', "/products/{$productId}/images", $payload));
    }

    public function deleteProductImage(int $productId, int $imageId): void
    {
        $path = "/products/{$productId}/images/{$imageId}";
        $this->assertOk($this->send('DELETE', $path), 'DELETE', $path);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return \Generator<int, list<array<string, mixed>>>
     */
    public function paginatePath(string $path, array $query = []): \Generator
    {
        $perPage = (int) ($query['per_page'] ?? config('tiendanube.per_page', 50));
        $query['per_page'] = $perPage;
        $query['page'] = (int) ($query['page'] ?? 1);
        $url = $path;
        $data = $query;
        $guard = 0;

        while (true) {
            // ponytail: tope de páginas para no seguir Link/page++ sin fin; upgrade: usar x-total-count.
            if ($guard++ > 10_000) {
                throw new TiendanubeApiRouteException(
                    'Tiendanube API: paginación excedió el tope de páginas.',
                    0,
                    $path,
                    'GET',
                    'pagination_limit',
                );
            }

            $response = $this->send('GET', $url, $data);
            $chunk = $this->decodeListOrFail($response, $path);
            yield $chunk;

            $next = $this->nextPageUrlFrom($response);
            if ($next !== null) {
                $this->assertAllowedPaginationUrl($next);
                if ($chunk === []) {
                    return;
                }
                $url = $next;
                $data = [];

                continue;
            }

            // ponytail: fallback page++ si la API no envía Link (techo: página incompleta termina);
            // upgrade: exigir header Link en 2025-03.
            if (count($chunk) < $perPage) {
                return;
            }

            $query['page'] = (int) $query['page'] + 1;
            $url = $path;
            $data = $query;
        }
    }

    public function nextPageUrlFrom(Response $response): ?string
    {
        $header = $response->header('Link');
        if (! is_string($header) || $header === '') {
            $links = $response->toPsrResponse()->getHeader('Link');
            $header = $links[0] ?? '';
        }
        if ($header === '') {
            return null;
        }

        foreach (explode(',', $header) as $part) {
            if (preg_match('/<([^>]+)>\s*;\s*rel="?next"?/i', trim($part), $m)) {
                return $m[1];
            }
        }

        return null;
    }

    public function assertAllowedPaginationUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        $expectedHost = $this->configuredHost();
        $storeId = (string) $this->config()->store_id;
        $version = $this->configuredVersion();

        if ($scheme !== 'https' || $host === '' || $host !== $expectedHost) {
            throw new TiendanubeApiRouteException(
                'Tiendanube API: Link de paginación con host no permitido.',
                0,
                $url,
                'GET',
                'pagination_host',
            );
        }

        $needle = '/'.$version.'/'.$storeId;
        if (! str_contains($path, $needle)) {
            throw new TiendanubeApiRouteException(
                'Tiendanube API: Link de paginación con tienda o versión no permitidas.',
                0,
                $url,
                'GET',
                'pagination_path',
            );
        }
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
     * @param  array<string, mixed>  $data
     */
    private function send(string $method, string $url, array $data = []): Response
    {
        $method = strtoupper($method);

        try {
            $pending = $this->client($method, $url);
            $response = match ($method) {
                'GET' => $pending->get($url, $data),
                'POST' => $pending->post($url, $data),
                'PUT' => $pending->put($url, $data),
                'DELETE' => $pending->delete($url, $data),
                default => throw new InvalidArgumentException("Método HTTP no soportado: {$method}"),
            };
        } catch (ConnectionException $e) {
            throw new TiendanubeApiException(
                'Tiendanube API conexión fallida (timeout o red).',
                0,
                $this->resourceFromUrl($url),
                $method,
                'connection_failed',
                null,
                $e,
            );
        }

        $this->assertOk($response, $method, $url);

        return $response;
    }

    private function shouldRetry(Throwable $exception, string $method, string $path): bool
    {
        $status = null;
        if ($exception instanceof RequestException && $exception->response) {
            $status = $exception->response->status();
        }

        $uncertainPost = $method === 'POST' && $this->isUncertainPostPath($path);

        if ($exception instanceof ConnectionException) {
            return in_array($method, ['GET', 'HEAD'], true);
        }

        if ($status === 429) {
            return true;
        }

        if (in_array($status, [502, 503, 504], true)) {
            if ($uncertainPost) {
                return false;
            }

            return in_array($method, ['GET', 'HEAD', 'PUT', 'DELETE'], true);
        }

        return false;
    }

    private function isUncertainPostPath(string $path): bool
    {
        $resource = $this->resourceFromUrl($path);

        return (bool) preg_match('#^/(products|webhooks)(/|$)#', $resource)
            || str_contains($resource, '/images');
    }

    private function decode(Response $response): array
    {
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

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeListOrFail(Response $response, string $path): array
    {
        $json = $response->json();
        if ($json === null && trim((string) $response->body()) === '') {
            return [];
        }
        if (! is_array($json) || ! array_is_list($json)) {
            throw new TiendanubeApiException(
                'Tiendanube API: listado no es un array JSON.',
                $response->status(),
                $this->resourceFromUrl($path),
                'GET',
                'invalid_list',
            );
        }

        return $json;
    }

    private function assertOk(Response $response, string $method, string $path): void
    {
        if ($response->successful()) {
            return;
        }

        $resource = $this->resourceFromUrl($path);
        $json = $response->json();
        $detalle = is_array($json) ? $this->formatearErrorApi($json) : mb_substr((string) $response->body(), 0, 500);
        $summary = mb_substr($detalle, 0, 240);
        $message = "Tiendanube API HTTP {$response->status()}: {$detalle}";
        $body = is_string($response->body()) ? mb_substr($response->body(), 0, 500) : null;
        $status = $response->status();

        $exception = match (true) {
            $status === 402 => TiendanubeApiPaymentRequiredException::class,
            $status === 422 => TiendanubeApiValidationException::class,
            $status === 429 => TiendanubeApiRateLimitException::class,
            $status === 404 && $this->isResourceNotFound($response, $resource) => TiendanubeApiNotFoundException::class,
            $status === 404 => TiendanubeApiRouteException::class,
            default => TiendanubeApiException::class,
        };

        throw new $exception($message, $status, $resource, $method, $summary, $body);
    }

    private function isResourceNotFound(Response $response, string $resource): bool
    {
        $contentType = strtolower((string) $response->header('Content-Type'));
        $isJson = str_contains($contentType, 'json') || is_array($response->json());
        $hasId = (bool) preg_match('#/(products|categories|webhooks|images|variants)/\d+#', $resource);

        return $hasId && $isJson && ! str_contains($contentType, 'text/html');
    }

    private function resourceFromUrl(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
        $stripped = preg_replace('#^/(?:v1|2025-03)/\d+#', '', $path);

        return is_string($stripped) && $stripped !== '' ? $stripped : $path;
    }

    private function assertAllowedVersion(string $version): void
    {
        if (! in_array($version, self::ALLOWED_VERSIONS, true)) {
            throw new InvalidArgumentException("Versión de API Tiendanube desconocida: {$version}.");
        }
    }

    private function assertValidApiBase(string $base): void
    {
        $path = (string) (parse_url($base, PHP_URL_PATH) ?: '');
        $segments = array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));
        $prevVersion = null;

        foreach ($segments as $seg) {
            $looksLikeVersion = (bool) preg_match('/^v\d+$/', $seg) || (bool) preg_match('/^\d{4}-\d{2}$/', $seg);
            if (! $looksLikeVersion) {
                continue;
            }
            if (! in_array($seg, self::ALLOWED_VERSIONS, true)) {
                throw new InvalidArgumentException("Versión de API Tiendanube desconocida: {$seg}.");
            }
            if ($prevVersion !== null) {
                throw new InvalidArgumentException("TIENDANUBE_API_BASE duplica la versión de API ({$prevVersion}/{$seg}).");
            }
            $prevVersion = $seg;
        }
    }

    /** @param array<string, mixed> $json */
    private function formatearErrorApi(array $json): string
    {
        $partes = [];

        foreach (['message', 'description', 'code', 'error'] as $clave) {
            if (! empty($json[$clave]) && is_scalar($json[$clave])) {
                $partes[] = (string) $json[$clave];
            }
        }

        foreach ($json as $campo => $valor) {
            if (in_array($campo, ['message', 'description', 'code', 'error'], true)) {
                continue;
            }
            if (is_array($valor)) {
                $msgs = array_values(array_filter($valor, fn ($v) => is_scalar($v)));
                if ($msgs !== []) {
                    $partes[] = $campo.': '.implode(' | ', array_map('strval', $msgs));
                }
            }
        }

        return $partes !== [] ? implode(' — ', $partes) : (string) json_encode($json, JSON_UNESCAPED_UNICODE);
    }
}
