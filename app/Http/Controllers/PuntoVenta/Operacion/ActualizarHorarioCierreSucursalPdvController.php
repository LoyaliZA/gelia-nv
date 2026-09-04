<?php

namespace App\Http\Controllers\PuntoVenta\Operacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Operacion\ActualizarHorarioCierreSucursalPdvRequest;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\ActualizarHorarioCierreSucursalPdvService;
use Illuminate\Http\JsonResponse;

class ActualizarHorarioCierreSucursalPdvController extends Controller
{
    public function __invoke(
        ActualizarHorarioCierreSucursalPdvRequest $request,
        ActualizarHorarioCierreSucursalPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $datos = $request->payloadOperacion();

        $horario = $servicio->ejecutar(
            $user,
            $datos['hora_cierre'],
            $datos['zona_horaria'],
        );

        return response()->json([
            'horario_cierre' => $horario,
        ]);
    }
}
