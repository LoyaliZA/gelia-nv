<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarIncidenciaResguardoPdvRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\RegistrarIncidenciaResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\SerializadorIncidenciaResguardoPdv;
use Illuminate\Http\JsonResponse;

class RegistrarIncidenciaResguardoPdvController extends Controller
{
    public function __invoke(
        RegistrarIncidenciaResguardoPdvRequest $request,
        ResguardoPdv $resguardo,
        RegistrarIncidenciaResguardoPdvService $registrar,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $datos = $request->payloadOperacion();

        $resultado = $registrar->ejecutar(
            $resguardo,
            $user,
            (int) $datos['version'],
            (string) $datos['idempotency_key'],
            (string) $datos['tipo'],
            (string) $datos['descripcion'],
            $request->file('evidencias', []) ?? [],
            isset($datos['bulto_id']) ? (int) $datos['bulto_id'] : null,
            $datos['bulto'] ?? null,
            isset($datos['almacen_id']) ? (int) $datos['almacen_id'] : null,
        );

        return response()->json([
            'resguardo' => SerializadorIncidenciaResguardoPdv::resguardo($resultado['resguardo']),
            'incidencia' => SerializadorIncidenciaResguardoPdv::incidencia($resultado['incidencia']),
        ]);
    }
}
