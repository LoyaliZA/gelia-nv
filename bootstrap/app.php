<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        
        // CORRECCIÓN: Usamos render() para interceptar el error crudo antes de la vista HTML
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            
            if ($request->header('X-Inertia')) {
                // Determinamos si es un error HTTP válido (como 403 o 404)
                $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface 
                            ? $e->getStatusCode() 
                            : 500;

                if (in_array($status, [403, 404, 500, 503])) {
                    return \Inertia\Inertia::render('Error', ['status' => $status])
                        ->toResponse($request)
                        ->setStatusCode($status);
                }
            }
            
            return null; // Deja que Laravel siga su curso si no es Inertia
        });

    })->create();