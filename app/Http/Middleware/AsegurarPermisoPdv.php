<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AsegurarPermisoPdv
{
    public function __construct(private readonly AlcancePdv $alcance)
    {
    }

    public function handle(Request $request, Closure $next, string $permiso): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $this->alcance->tienePermisoPdv($user, $permiso)) {
            abort(403);
        }

        return $next($request);
    }
}
