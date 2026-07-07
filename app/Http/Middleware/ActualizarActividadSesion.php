<?php

namespace App\Http\Middleware;

use App\Services\Auditoria\RegistrarAuditoriaAccesoService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActualizarActividadSesion
{
    public function __construct(
        private readonly RegistrarAuditoriaAccesoService $auditoriaAcceso
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->hasSession()) {
            $this->auditoriaAcceso->actualizarActividad($request->session()->getId());
        }

        return $next($request);
    }
}
