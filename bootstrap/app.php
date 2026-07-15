<?php

use App\Support\FormPublicUrl;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // Solo si APP_ALLOWED_HOSTS está definido (producción). En testing/local no restringe.
        if (is_string(env('APP_ALLOWED_HOSTS')) && trim((string) env('APP_ALLOWED_HOSTS')) !== '') {
            $middleware->trustHosts(
                at: fn () => FormPublicUrl::allowedHosts(),
                subdomains: false,
            );
        }

        $middleware->web(prepend: [
            \App\Http\Middleware\RestrictFormHostname::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\ActualizarActividadSesion::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'api.app' => \App\Http\Middleware\AuthenticateApiApplication::class,
            'log.api' => \App\Http\Middleware\LogApiRequest::class,
            'api.resource' => \App\Http\Middleware\CheckApiResourcePermission::class,
            'require.json' => \App\Http\Middleware\RequireJsonAccept::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return response()->json(['message' => 'No autorizado.'], 401);
        });

        $exceptions->respond(function (Response $response, \Throwable $exception, \Illuminate\Http\Request $request) {
            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            if ($request->header('X-Inertia')) {
                return Inertia::location($request->fullUrl());
            }

            return $response;
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, \Illuminate\Http\Request $request) {
            if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
                return null;
            }

            $referer = $request->headers->get('referer');
            $current = $request->fullUrl();
            $destino = route('dashboard', absolute: false);

            if ($referer && $referer !== $current) {
                $refererPath = parse_url($referer, PHP_URL_PATH);
                $currentPath = $request->getPathInfo();
                if ($refererPath && $refererPath !== $currentPath) {
                    $destino = $referer;
                }
            }

            if (preg_match('#^/solicitudes/\d+/#', $request->getPathInfo())) {
                $destino = route('solicitudes.index', absolute: false);
            }

            if ($request->header('X-Inertia')) {
                return redirect($destino);
            }

            return redirect($destino);
        });

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (! $request->header('X-Inertia')) {
                return null;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }

            $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            if (! in_array($status, [403, 404, 500, 503], true)) {
                return null;
            }

            return \Inertia\Inertia::render('Error', [
                'status' => $status,
                'returnUrl' => $request->headers->get('referer'),
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });

    })->create();