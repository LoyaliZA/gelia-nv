<?php

namespace App\Http\Controllers\PuntoVenta\Operacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Operacion\ReabrirSucursalPdvRequest;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\ReabrirSucursalPdvService;
use Illuminate\Http\JsonResponse;

class ReabrirSucursalPdvController extends Controller
{
    public function __invoke(
        ReabrirSucursalPdvRequest $request,
        ReabrirSucursalPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $dia = $servicio->ejecutar($user, $request->versionEsperada(), now());

        return response()->json([
            'sucursal_dia' => [
                'id' => $dia->id,
                'acepta_altas' => $dia->acepta_altas,
                'cierre_manual_at' => $dia->cierre_manual_at?->toIso8601String(),
                'cierre_automatico_invalidado' => $dia->cierre_automatico_invalidado,
                'version' => $dia->version,
            ],
        ]);
    }
}
