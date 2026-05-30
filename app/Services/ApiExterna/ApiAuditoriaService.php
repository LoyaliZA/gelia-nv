<?php

namespace App\Services\ApiExterna;

use App\Models\ApiAuditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiAuditoriaService
{
    private const SENSITIVE_KEYS = [
        'client_secret',
        'password',
        'access_token',
        'token',
        'authorization',
    ];

    public function iniciarRequest(Request $request): string
    {
        $requestId = (string) Str::uuid();
        $request->attributes->set('api_request_id', $requestId);
        $request->attributes->set('api_request_start', microtime(true));

        return $requestId;
    }

    public function registrar(
        Request $request,
        ?int $aplicacionId,
        int $statusCode,
        ?string $errorResumen = null
    ): void {
        $inicio = $request->attributes->get('api_request_start', microtime(true));
        $duracionMs = (int) round((microtime(true) - $inicio) * 1000);

        ApiAuditoria::create([
            'api_aplicacion_id' => $aplicacionId,
            'metodo' => $request->method(),
            'ruta' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
            'query_params' => $this->sanitizarParams($request),
            'request_id' => $request->attributes->get('api_request_id'),
            'status_code' => $statusCode,
            'duracion_ms' => $duracionMs,
            'error_resumen' => $errorResumen ? Str::limit($errorResumen, 255) : null,
            'created_at' => now(),
        ]);
    }

    private function sanitizarParams(Request $request): array
    {
        $params = array_merge(
            $request->query(),
            $request->except(array_merge(self::SENSITIVE_KEYS, ['_token']))
        );

        foreach (self::SENSITIVE_KEYS as $key) {
            if (array_key_exists($key, $params)) {
                $params[$key] = '[REDACTED]';
            }
        }

        return $params;
    }
}
