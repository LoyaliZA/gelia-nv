<?php

namespace App\Http\Controllers\PuntoVenta\Turnos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Turnos\CerrarAtencionTurnoPdvRequest;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Turnos\CerrarAtencionTurnoPdvService;
use App\Support\PuntoVenta\Turnos\SerializadorTurnoPdv;
use Illuminate\Http\JsonResponse;

class CerrarAtencionTurnoPdvController extends Controller
{
    public function __invoke(
        CerrarAtencionTurnoPdvRequest $request,
        TurnoPdv $turno,
        CerrarAtencionTurnoPdvService $servicio,
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
            'atencion' => SerializadorTurnoPdv::atencion($resultado['atencion']),
            'evento' => [
                'id' => $resultado['evento']->id,
                'tipo_evento' => $resultado['evento']->tipo_evento,
            ],
        ]);
    }
}
