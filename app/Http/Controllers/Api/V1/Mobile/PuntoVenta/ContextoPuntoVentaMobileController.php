<?php

namespace App\Http\Controllers\Api\V1\Mobile\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContextoPuntoVentaMobileController extends Controller
{
    public function show(Request $request, AlcancePdv $alcance): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->payload($user, $alcance));
    }

    public function establecerSucursal(Request $request, AlcancePdv $alcance): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $datos = $request->validate([
            'sucursal_id' => ['required', 'integer'],
        ]);

        try {
            $alcance->establecerSucursalActiva($user, (int) $datos['sucursal_id']);
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->json($this->payload($user, $alcance));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user, AlcancePdv $alcance): array
    {
        $user->loadMissing('sucursales');

        $operables = $user->sucursales
            ->filter(static fn (Sucursal $sucursal): bool => $sucursal->activo && (bool) $sucursal->pivot->activo)
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(static fn (Sucursal $sucursal): array => [
                'id' => $sucursal->id,
                'nombre' => $sucursal->nombre,
                'es_principal' => (bool) $sucursal->pivot->es_principal,
            ])
            ->values();

        $activaId = $alcance->sucursalActivaId($user);

        return [
            'sucursal_activa' => $operables->firstWhere('id', $activaId),
            'sucursales_operables' => $operables->all(),
            'permisos' => [
                'resguardos_ver' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER),
                'resguardos_recibir_gerente' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR_GERENTE),
                'resguardos_entregar' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR),
                'resguardos_ver_rezagados' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER_REZAGADOS),
                'turnos_ver' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_VER),
                'turnos_alta' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_ALTA),
            ],
        ];
    }
}
