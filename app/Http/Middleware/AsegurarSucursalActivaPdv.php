<?php

namespace App\Http\Middleware;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AsegurarSucursalActivaPdv
{
    public function __construct(private readonly ResuelveAlcancePdv $alcance)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || $this->alcance->sucursalActivaId($user) === null) {
            abort(403, 'No tiene sucursal activa para operar en piso.');
        }

        return $next($request);
    }
}
