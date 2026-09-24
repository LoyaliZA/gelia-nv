<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\AutorizacionConsultaResguardoPdv;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AsegurarLecturaResguardoPdv
{
    public function __construct(
        private readonly AlcancePdv $alcance,
        private readonly AutorizacionConsultaResguardoPdv $autorizacion,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $puedeVerBandeja = $this->alcance->permiteConsultaPiso($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER);
        $puedeHistorial = $this->autorizacion->permiteHistorialEntregados($user);

        if (! $puedeVerBandeja && ! $puedeHistorial) {
            abort(403);
        }

        return $next($request);
    }
}
