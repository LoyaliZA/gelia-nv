<?php

namespace App\Http\Controllers\PuntoVenta\Operacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Operacion\AmpliarHorarioSucursalPdvRequest;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\AmpliarHorarioSucursalPdvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AmpliarHorarioSucursalPdvController extends Controller
{
    public function __invoke(
        AmpliarHorarioSucursalPdvRequest $request,
        AmpliarHorarioSucursalPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $datos = $request->payloadOperacion();

        $dia = $servicio->ejecutar(
            $user,
            $datos['version'],
            Carbon::parse($datos['ampliacion_hasta_at']),
            now(),
        );

        return response()->json([
            'sucursal_dia' => [
                'id' => $dia->id,
                'acepta_altas' => $dia->acepta_altas,
                'ampliacion_hasta_at' => $dia->ampliacion_hasta_at?->toIso8601String(),
                'cierre_automatico_invalidado' => $dia->cierre_automatico_invalidado,
                'version' => $dia->version,
            ],
        ]);
    }
}
