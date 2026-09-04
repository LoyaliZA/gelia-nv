<?php

namespace App\Http\Controllers\PuntoVenta\Operacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Operacion\AbrirJornadaPdvRequest;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\AbrirJornadaPdvService;
use Illuminate\Http\JsonResponse;

class AbrirJornadaPdvController extends Controller
{
    public function __invoke(
        AbrirJornadaPdvRequest $request,
        AbrirJornadaPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $resultado = $servicio->ejecutar($user, now());

        return response()->json([
            'jornada' => [
                'id' => $resultado['jornada']->id,
                'estado' => $resultado['jornada']->estado->value,
                'apertura_at' => $resultado['jornada']->apertura_at?->toIso8601String(),
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
