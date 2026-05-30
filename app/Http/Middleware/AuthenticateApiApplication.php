<?php

namespace App\Http\Middleware;

use App\Models\ApiAplicacion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiApplication
{
    public function handle(Request $request, Closure $next): Response
    {
        $aplicacion = $request->user();

        if (!$aplicacion instanceof ApiAplicacion) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        if (!$aplicacion->activa) {
            return response()->json(['message' => 'Aplicación desactivada.'], 403);
        }

        if (!$aplicacion->ipPermitida($request->ip())) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $request->attributes->set('api_aplicacion', $aplicacion);

        return $next($request);
    }
}
