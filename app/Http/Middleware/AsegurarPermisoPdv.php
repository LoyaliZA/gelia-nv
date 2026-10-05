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

    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        foreach ($permisos as $permiso) {
            if ($this->alcance->tienePermisoPdv($user, $permiso)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
