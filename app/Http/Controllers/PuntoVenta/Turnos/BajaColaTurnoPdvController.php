<?php

namespace App\Http\Controllers\PuntoVenta\Turnos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Turnos\BajaColaTurnoPdvRequest;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Turnos\BajaColaTurnoPdvService;
use App\Support\PuntoVenta\Turnos\SerializadorTurnoPdv;
use Illuminate\Http\JsonResponse;

class BajaColaTurnoPdvController extends Controller
{
    public function __invoke(
        BajaColaTurnoPdvRequest $request,
        TurnoPdv $turno,
        BajaColaTurnoPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $datos = $request->payloadOperacion();

        $resultado = $servicio->ejecutar(
            $turno,
            $user,
            $datos['version'],
            $datos['idempotency_key'],
            $datos['motivo'],
            $datos['motivo_detalle'],
            now(),
        );

        return response()->json([
            'turno' => SerializadorTurnoPdv::turno($resultado['turno']),
            'evento' => [
                'id' => $resultado['evento']->id,
                'tipo_evento' => $resultado['evento']->tipo_evento,
            ],
        ]);
    }
}
