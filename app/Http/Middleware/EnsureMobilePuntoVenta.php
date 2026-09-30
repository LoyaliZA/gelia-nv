<?php

namespace App\Http\Middleware;

use App\Models\MobileDevice;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobilePuntoVenta
{
    public function __construct(
        private readonly PuntoVentaModulo $modulo,
        private readonly AlcancePdv $alcance,
    ) {}

    public function handle(Request $request, Closure $next, string $exigirSucursal = ''): Response
    {
        if (! $this->modulo->habilitado()) {
            return response()->json(['message' => 'Punto de venta no está habilitado.'], 404);
        }

        $user = $request->user();
        if (! $user instanceof User || ! $this->alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_ACCEDER)) {
            return response()->json(['message' => 'No tiene permiso para acceder a Punto de Venta.'], 403);
        }

        $device = $request->attributes->get('mobile_device');
        if (! $device instanceof MobileDevice) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        if ($exigirSucursal === 'operacion' && $this->alcance->sucursalActivaId($user) === null) {
            return response()->json([
                'message' => 'Seleccione la sucursal activa del dispositivo.',
                'code' => 'sucursal_activa_requerida',
            ], 409);
        }

        return $next($request);
    }
}
