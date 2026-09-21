<?php

namespace App\Http\Controllers\PuntoVenta\Operacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Operacion\ConsultarPlazosTurnosPdvRequest;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use Illuminate\Http\JsonResponse;

class ConsultarPlazosTurnosPdvController extends Controller
{
    public function __invoke(
        ConsultarPlazosTurnosPdvRequest $request,
        PlazosTurnosPdvConfig $plazos,
    ): JsonResponse {
        return response()->json([
            'plazos_turnos' => $plazos->obtenerOPredeterminado(),
        ]);
    }
}
