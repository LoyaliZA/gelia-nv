<?php

namespace App\Http\Controllers\PuntoVenta\Operacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Operacion\FinalizarPausaPdvRequest;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\FinalizarPausaPdvService;
use Illuminate\Http\JsonResponse;

class FinalizarPausaPdvController extends Controller
{
    public function __invoke(
        FinalizarPausaPdvRequest $request,
        FinalizarPausaPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $resultado = $servicio->ejecutar($user, now());

        return response()->json([
            'jornada' => [
                'id' => $resultado['jornada']->id,
                'estado' => $resultado['jornada']->estado->value,
                'version' => $resultado['jornada']->version,
            ],
            'intervalo' => [
                'id' => $resultado['intervalo']->id,
                'tipo' => $resultado['intervalo']->tipo?->value,
                'inicio_at' => $resultado['intervalo']->inicio_at?->toIso8601String(),
            ],
            'reintento' => $resultado['reintento'],
        ]);
    }
}
