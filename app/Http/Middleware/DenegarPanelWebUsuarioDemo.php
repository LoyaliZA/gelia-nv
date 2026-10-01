<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoriaAccesoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DenegarPanelWebUsuarioDemo
{
    public const MENSAJE = 'Esta cuenta no tiene acceso al sistema interno.';

    public function __construct(
        private readonly RegistrarAuditoriaAccesoService $auditoriaAcceso,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->es_demo) {
            return $next($request);
        }

        $sessionId = $request->session()->getId();
        $this->auditoriaAcceso->registrarCierre($sessionId, 'logout');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'login' => self::MENSAJE,
        ]);
    }
}
