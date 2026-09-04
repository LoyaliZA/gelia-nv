<?php

namespace App\Http\Controllers\PuntoVenta\Turnos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Turnos\TransferirTurnoPdvRequest;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Turnos\TransferirTurnoPdvService;
use App\Support\PuntoVenta\Turnos\SerializadorTurnoPdv;
use Illuminate\Http\JsonResponse;

class TransferirTurnoPdvController extends Controller
{
    public function __invoke(
        TransferirTurnoPdvRequest $request,
        TurnoPdv $turno,
        TransferirTurnoPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $datos = $request->payloadOperacion();

        $resultado = $servicio->ejecutar(
            $turno,
            $user,
            $datos['version'],
            $datos['idempotency_key'],
            $datos['destino_user_id'],
            now(),
        );

        return response()->json([
            'turno' => SerializadorTurnoPdv::turno($resultado['turno']),
            'atencion_anterior' => SerializadorTurnoPdv::atencion($resultado['atencion_anterior']),
            'atencion_nueva' => SerializadorTurnoPdv::atencion($resultado['atencion_nueva']),
            'evento' => [
                'id' => $resultado['evento']->id,
                'tipo_evento' => $resultado['evento']->tipo_evento,
            ],
        ]);
    }
}
