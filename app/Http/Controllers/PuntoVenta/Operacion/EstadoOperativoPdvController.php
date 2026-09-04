<?php

namespace App\Http\Controllers\PuntoVenta\Operacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Operacion\ConsultaEstadoOperativoPdvRequest;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\ConsultaEstadoOperativoPdvService;
use Illuminate\Http\JsonResponse;

class EstadoOperativoPdvController extends Controller
{
    public function __invoke(
        ConsultaEstadoOperativoPdvRequest $request,
        ConsultaEstadoOperativoPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json($servicio->ejecutar($user, now()));
    }
}
