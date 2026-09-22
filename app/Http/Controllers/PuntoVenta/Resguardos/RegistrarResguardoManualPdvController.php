<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarResguardoManualPdvRequest;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\CrearResguardoManualPdvService;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;

class RegistrarResguardoManualPdvController extends Controller
{
    public function __invoke(
        RegistrarResguardoManualPdvRequest $request,
        CrearResguardoManualPdvService $crear,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $resguardo = $crear->ejecutar($user, $request->payloadOperacion());

        return response()->json([
            'resguardo' => [
                'id' => $resguardo->id,
                'estado' => $resguardo->estado,
                'estado_etiqueta' => EtiquetasResguardoPdv::etiquetaEstado($resguardo->estado),
                'version' => $resguardo->version,
                'snapshot_folio' => $resguardo->snapshot_folio,
                'cantidad_bultos_esperada' => (int) $resguardo->cantidad_bultos_esperada,
            ],
        ], 201);
    }
}
