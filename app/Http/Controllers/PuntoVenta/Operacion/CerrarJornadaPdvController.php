<?php

namespace App\Http\Controllers\PuntoVenta\Operacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Operacion\CerrarJornadaPdvRequest;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\CerrarJornadaPdvService;
use Illuminate\Http\JsonResponse;

class CerrarJornadaPdvController extends Controller
{
    public function __invoke(
        CerrarJornadaPdvRequest $request,
        CerrarJornadaPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $resultado = $servicio->ejecutar($user, $request->versionEsperada(), now());

        return response()->json([
            'jornada' => [
                'id' => $resultado['jornada']->id,
                'estado' => $resultado['jornada']->estado->value,
                'cierre_at' => $resultado['jornada']->cierre_at?->toIso8601String(),
                'version' => $resultado['jornada']->version,
            ],
            'estado_destino' => $resultado['estado_destino']->value,
            'reintento' => $resultado['reintento'],
        ]);
    }
}
