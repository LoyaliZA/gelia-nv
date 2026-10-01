<?php

use App\Http\Middleware\ActualizarActividadSesion;
use App\Http\Middleware\AsegurarLecturaResguardoPdv;
use App\Http\Middleware\AsegurarModuloPuntoVenta;
use App\Http\Middleware\AsegurarPermisoPdv;
use App\Http\Middleware\AsegurarSucursalActivaPdv;
use App\Http\Middleware\AuthenticateApiApplication;
use App\Http\Middleware\AuthenticateMobileUser;
use App\Http\Middleware\CheckApiResourcePermission;
use App\Http\Middleware\DenegarPanelWebUsuarioDemo;
use App\Http\Middleware\EnsureMobilePuntoVenta;
use App\Http\Middleware\EnsureMobileSyncScope;
use App\Http\Middleware\EnsureWebAuthnEnabled;
use App\Http\Middleware\FijarAlcanceDemo;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\RequireJsonAccept;
use App\Http\Middleware\RestrictFormHostname;
use App\Support\FormPublicUrl;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

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

        $middleware->validateCsrfTokens(except: [
            'webhooks/tiendanube',
        ]);

        $middleware->web(prepend: [
            RestrictFormHostname::class,
        ]);

        $middleware->web(append: [
            FijarAlcanceDemo::class,
            DenegarPanelWebUsuarioDemo::class,
            HandleInertiaRequests::class,
            ActualizarActividadSesion::class,
        ]);

        $middleware->api(append: [
            FijarAlcanceDemo::class,
        ]);

        $middleware->appendToPriorityList(
            AuthenticatesRequests::class,
            FijarAlcanceDemo::class,
        );

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'api.app' => AuthenticateApiApplication::class,
            'log.api' => LogApiRequest::class,
            'api.resource' => CheckApiResourcePermission::class,
            'require.json' => RequireJsonAccept::class,
            'api.mobile' => AuthenticateMobileUser::class,
            'mobile.pdv' => EnsureMobilePuntoVenta::class,
            'mobile.sync' => EnsureMobileSyncScope::class,
            'webauthn.enabled' => EnsureWebAuthnEnabled::class,
            'pdv.piso' => AsegurarSucursalActivaPdv::class,
            'pdv.modulo' => AsegurarModuloPuntoVenta::class,
            'pdv.permiso' => AsegurarPermisoPdv::class,
            'pdv.resguardos.lectura' => AsegurarLecturaResguardoPdv::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return response()->json(['message' => 'No autorizado.'], 401);
        });

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            if ($request->header('X-Inertia')) {
                return Inertia::location($request->fullUrl());
            }

            return $response;
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
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

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->header('X-Inertia')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            if (! in_array($status, [403, 404, 500, 503], true)) {
                return null;
            }

            return Inertia::render('Error', [
                'status' => $status,
                'returnUrl' => $request->headers->get('referer'),
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });

    })->create();
