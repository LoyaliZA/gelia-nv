<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AsegurarModuloPuntoVenta
{
    public function __construct(
        private readonly PuntoVentaModulo $modulo,
        private readonly AlcancePdv $alcance,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->modulo->habilitado()) {
            abort(404);
        }

        $user = $request->user();
        if (! $user instanceof User || ! $this->alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_ACCEDER)) {
            abort(403, 'No tiene permiso para acceder a Punto de Venta.');
        }

        return $next($request);
    }
}
