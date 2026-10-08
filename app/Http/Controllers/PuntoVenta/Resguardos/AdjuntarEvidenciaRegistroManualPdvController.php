<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\AdjuntarEvidenciaRegistroManualPdvRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\IngresarResguardoManualPdvService;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EvidenciaMinimaRegistroManualPdv;
use Illuminate\Http\JsonResponse;

class AdjuntarEvidenciaRegistroManualPdvController extends Controller
{
    public function __invoke(
        AdjuntarEvidenciaRegistroManualPdvRequest $request,
        ResguardoPdv $resguardo,
        IngresarResguardoManualPdvService $ingresar,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $resguardo = $ingresar->adjuntar($resguardo, $user, $request->payloadOperacion());

        return response()->json([
            'resguardo' => [
                'id' => $resguardo->id,
                'estado' => $resguardo->estado,
                'estado_etiqueta' => EtiquetasResguardoPdv::etiquetaEstado($resguardo->estado),
                'version' => $resguardo->version,
                'snapshot_folio' => $resguardo->snapshot_folio,
                'cantidad_bultos_esperada' => (int) $resguardo->cantidad_bultos_esperada,
                'evidencia_completa' => EvidenciaMinimaRegistroManualPdv::completa($resguardo),
            ],
        ]);
    }
}
