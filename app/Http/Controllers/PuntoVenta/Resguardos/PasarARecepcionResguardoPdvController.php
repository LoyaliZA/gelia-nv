<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\PasarARecepcionResguardoPdvRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\PasarARecepcionResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;

class PasarARecepcionResguardoPdvController extends Controller
{
    public function __invoke(
        PasarARecepcionResguardoPdvRequest $request,
        ResguardoPdv $resguardo,
        PasarARecepcionResguardoPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $datos = $request->payloadOperacion();

        $resguardo = $servicio->ejecutar(
            $resguardo,
            $user,
            (int) $datos['version'],
            (string) $datos['idempotency_key'],
        );

        return response()->json([
            'resguardo' => [
                'id' => $resguardo->id,
                'estado' => $resguardo->estado,
                'estado_etiqueta' => EtiquetasResguardoPdv::etiquetaEstado($resguardo->estado),
                'version' => $resguardo->version,
            ],
        ]);
    }
}
