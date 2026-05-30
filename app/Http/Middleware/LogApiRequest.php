<?php

namespace App\Http\Middleware;

use App\Models\ApiAplicacion;
use App\Services\ApiExterna\ApiAuditoriaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    public function __construct(
        protected ApiAuditoriaService $auditoriaService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->auditoriaService->iniciarRequest($request);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $aplicacion = $request->user();
        $aplicacionId = $aplicacion instanceof ApiAplicacion ? $aplicacion->id : null;

        $errorResumen = null;
        if ($response->getStatusCode() >= 400) {
            $content = json_decode($response->getContent(), true);
            $errorResumen = is_array($content) ? ($content['message'] ?? null) : null;
        }

        $this->auditoriaService->registrar(
            $request,
            $aplicacionId,
            $response->getStatusCode(),
            $errorResumen
        );
    }
}
