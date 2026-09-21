<?php

namespace App\Http\Controllers\PuntoVenta\Operacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Operacion\ActualizarPlazosTurnosPdvRequest;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\ActualizarPlazosTurnosPdvService;
use Illuminate\Http\JsonResponse;

class ActualizarPlazosTurnosPdvController extends Controller
{
    public function __invoke(
        ActualizarPlazosTurnosPdvRequest $request,
        ActualizarPlazosTurnosPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'plazos_turnos' => $servicio->ejecutar($user, $request->payloadOperacion()),
        ]);
    }
}
