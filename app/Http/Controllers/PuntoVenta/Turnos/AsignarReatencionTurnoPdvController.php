<?php

namespace App\Http\Controllers\PuntoVenta\Turnos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Turnos\AsignarReatencionTurnoPdvRequest;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Turnos\AsignarReatencionTurnoPdvService;
use App\Services\PuntoVenta\Turnos\ConsultaBandejaReatencionPdvService;
use App\Support\PuntoVenta\Turnos\SerializadorTurnoPdv;
use Illuminate\Http\JsonResponse;

class AsignarReatencionTurnoPdvController extends Controller
{
    public function __invoke(
        AsignarReatencionTurnoPdvRequest $request,
        TurnoPdv $turno,
        AsignarReatencionTurnoPdvService $servicio,
        ConsultaBandejaReatencionPdvService $bandeja,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $datos = $request->payloadOperacion();
        $ahora = now();

        $resultado = $servicio->ejecutar(
            $turno,
            $user,
            $datos['version'],
            $datos['idempotency_key'],
            $datos['destino_user_id'],
            $ahora,
        );

        return response()->json([
            'turno' => SerializadorTurnoPdv::turno($resultado['turno']),
            'atencion' => SerializadorTurnoPdv::atencion($resultado['atencion']),
            'evento' => [
                'id' => $resultado['evento']->id,
                'tipo_evento' => $resultado['evento']->tipo_evento,
            ],
            'reatencion' => $bandeja->listar($user, $ahora),
        ]);
    }
}
