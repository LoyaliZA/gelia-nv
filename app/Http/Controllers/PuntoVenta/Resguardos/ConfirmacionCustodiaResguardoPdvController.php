<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarConfirmacionCustodiaPdvRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\RegistrarConfirmacionCustodiaPdvService;
use App\Support\PuntoVenta\Resguardos\EstadoResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;

class ConfirmacionCustodiaResguardoPdvController extends Controller
{
    public function __invoke(
        RegistrarConfirmacionCustodiaPdvRequest $request,
        ResguardoPdv $resguardo,
        RegistrarConfirmacionCustodiaPdvService $registrar,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $datos = $request->payloadOperacion();

        $resguardo = $registrar->ejecutar(
            $resguardo,
            $user,
            (int) $datos['version'],
            (string) $datos['idempotency_key'],
            (int) $datos['almacen_id'],
            $datos['bultos'],
            $request->file('evidencias', []) ?? [],
        );

        return response()->json([
            'resguardo' => [
                'id' => $resguardo->id,
                'estado' => $resguardo->estado,
                'estado_etiqueta' => EtiquetasResguardoPdv::etiquetaEstado($resguardo->estado),
                'version' => $resguardo->version,
                'custodia_confirmada_at' => $resguardo->custodia_confirmada_at?->toIso8601String(),
                'cantidad_bultos_esperada' => (int) $resguardo->cantidad_bultos_esperada,
                'cantidad_bultos_en_custodia' => EstadoResguardoPdv::cantidadEnCustodia($resguardo),
                'cantidad_bultos_pendiente_custodia' => EstadoResguardoPdv::cantidadPendienteCustodia($resguardo),
                'custodia_completa' => EstadoResguardoPdv::custodiaCompleta($resguardo),
                'almacen_id' => $resguardo->almacen_id,
                'bultos' => $resguardo->bultos->map(fn ($bulto) => [
                    'id' => $bulto->id,
                    'folio' => $bulto->folio,
                    'tipo' => $bulto->tipo,
                    'estado' => $bulto->estado,
                    'custodia_at' => $bulto->custodia_at?->toIso8601String(),
                ])->values()->all(),
            ],
        ]);
    }
}
