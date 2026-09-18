<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Mobile\MobileClienteAlcanceService;
use App\Services\Mobile\MobileScopeVersionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileSyncScope
{
    public function __construct(
        protected MobileClienteAlcanceService $alcance,
        protected MobileScopeVersionService $scopeVersion
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->alcance->tieneAccesoMovil($user)) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $actual = $this->scopeVersion->compute($user);
        $enviada = $request->headers->get('X-Mobile-Scope-Version')
            ?: $request->query('scope_version');

        if (is_string($enviada) && $enviada !== '' && $enviada !== $actual) {
            return response()->json([
                'code' => 'scope_changed',
                'action' => 'bootstrap',
                'scope_version' => $actual,
            ], 409);
        }

        $request->attributes->set('mobile_scope_version', $actual);

        return $next($request);
    }
}
