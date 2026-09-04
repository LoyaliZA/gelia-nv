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
        if (! $user instanceof User) {
            abort(403, 'No tiene sucursal activa para operar en piso.');
        }

        if ($this->alcance->sucursalActivaId($user) !== null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            $mensaje = $this->alcance->idsSucursalesOperables($user)->isEmpty()
                ? 'No tiene sucursal asignada para operar en piso.'
                : 'Debe seleccionar una sucursal activa para operar en piso.';

            abort(403, $mensaje);
        }

        return redirect()->route('punto_venta.alcance.configurar');
    }
}
