<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\ResolverIncidenciaResguardoPdvRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\ResolverIncidenciaResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\SerializadorIncidenciaResguardoPdv;
use Illuminate\Http\JsonResponse;

class ResolverIncidenciaResguardoPdvController extends Controller
{
    public function __invoke(
        ResolverIncidenciaResguardoPdvRequest $request,
        ResguardoPdv $resguardo,
        ResguardoPdvIncidencia $incidenciaResguardo,
        ResolverIncidenciaResguardoPdvService $resolver,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $datos = $request->payloadOperacion();

        $resultado = $resolver->ejecutar(
            $resguardo,
            $incidenciaResguardo,
            $user,
            (int) $datos['version'],
            (int) $datos['incidencia_version'],
            (string) $datos['idempotency_key'],
            (string) $datos['motivo_resolucion'],
        );

        return response()->json([
            'resguardo' => SerializadorIncidenciaResguardoPdv::resguardo($resultado['resguardo']),
            'incidencia' => SerializadorIncidenciaResguardoPdv::incidencia($resultado['incidencia']),
        ]);
    }
}
