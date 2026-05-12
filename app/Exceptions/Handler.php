<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Inertia\Inertia; // <-- Asegúrate de importar Inertia aquí arriba

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // --- AQUÍ INTERCEPTAMOS LOS ERRORES PARA INERTIA ---
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            // Obtenemos el código de estado (403, 404, 500, etc.)
            $status = $response->getStatusCode();

            // Si la petición viene de Inertia y es un error común, mostramos nuestra pantalla
            if ($request->header('X-Inertia') && in_array($status, [403, 404, 500, 503])) {
                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();