<?php

namespace App\Http\Controllers\PuntoVenta\Turnos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Turnos\IniciarAtencionTurnoPdvRequest;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Turnos\IniciarAtencionTurnoPdvService;
use App\Support\PuntoVenta\Turnos\SerializadorTurnoPdv;
use Illuminate\Http\JsonResponse;

class IniciarAtencionTurnoPdvController extends Controller
{
    public function __invoke(
        IniciarAtencionTurnoPdvRequest $request,
        TurnoPdv $turno,
        IniciarAtencionTurnoPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $resultado = $servicio->ejecutar(
            $turno,
            $user,
            $request->versionEsperada(),
            now(),
        );

        return response()->json([
            'turno' => SerializadorTurnoPdv::turno($resultado['turno']),
            'atencion' => SerializadorTurnoPdv::atencion($resultado['atencion']),
        ]);
    }
}
