<?php

namespace App\Http\Middleware;

use App\Models\ApiAplicacion;
use App\Services\ApiExterna\ApiPermisoService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiResourcePermission
{
    public function __construct(
        protected ApiPermisoService $permisoService
    ) {}

    public function handle(Request $request, Closure $next, string $recursoSlug, string $accion = 'read'): Response
    {
        $aplicacion = $request->user();

        if (!$aplicacion instanceof ApiAplicacion) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        $recurso = $this->permisoService->recursoPorSlug($recursoSlug);

        if (!$recurso) {
            return response()->json(['message' => 'Recurso no disponible.'], 404);
        }

        $permitido = $accion === 'write'
            ? $this->permisoService->puedeEscribir($aplicacion, $recurso)
            : $this->permisoService->puedeLeer($aplicacion, $recurso);

        if (!$permitido) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $request->attributes->set('api_recurso', $recurso);

        return $next($request);
    }
}
