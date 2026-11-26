<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las rutas móviles reutilizan controladores web que, según el cliente, responden
 * JSON, redirección con mensaje flash o una pantalla Inertia. Este middleware
 * fuerza la rama JSON y traduce lo que no lo sea, para que la app móvil nunca
 * reciba HTML ni redirecciones.
 */
class ResponderJsonMovil
{
    public function handle(Request $request, Closure $next): Response
    {
        // Con «Accept: */*» expectsJson() es falso y el controlador web respondería HTML.
        $request->headers->set('Accept', 'application/json');

        $respuesta = $next($request);

        if ($respuesta instanceof RedirectResponse) {
            return $this->traducirRedireccion($respuesta);
        }

        $tipo = (string) $respuesta->headers->get('Content-Type', '');
        if (str_starts_with($tipo, 'text/html') && $respuesta->getStatusCode() < 400) {
            return response()->json([
                'message' => 'Esta ruta no tiene una versión JSON para la app móvil.',
                'code' => 'sin_version_json',
            ], 406);
        }

        return $respuesta;
    }

    private function traducirRedireccion(RedirectResponse $redireccion): Response
    {
        $sesion = $redireccion->getSession();
        $respuesta = $this->mensajeDesdeSesion($sesion);
        // El flash no debe sobrevivir a esta petición (workers de larga vida).
        $sesion?->forget(['errors', 'error', 'success', 'message']);

        return $respuesta;
    }

    private function mensajeDesdeSesion(?Session $sesion): Response
    {

        $errores = $sesion?->get('errors');
        if ($errores instanceof ViewErrorBag && $errores->any()) {
            $bolsa = $errores->getBag('default');

            return response()->json([
                'message' => (string) $bolsa->first(),
                'errors' => $bolsa->toArray(),
            ], 422);
        }

        $error = $sesion?->get('error');
        if (is_string($error) && $error !== '') {
            return response()->json(['message' => $error], 422);
        }

        $exito = $sesion?->get('success') ?? $sesion?->get('message');

        return response()->json([
            'ok' => true,
            'message' => is_string($exito) && $exito !== '' ? $exito : 'Operación realizada.',
        ]);
    }
}
