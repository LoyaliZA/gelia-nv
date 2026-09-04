<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;

class EntregaResguardoPdvController extends Controller
{
    public function __invoke(
        RegistrarEntregaResguardoPdvRequest $request,
        ResguardoPdv $resguardo,
        RegistrarEntregaResguardoPdvService $registrar,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $datos = $request->payloadOperacion();

        $resguardo = $registrar->ejecutar(
            $resguardo,
            $user,
            (int) $datos['version'],
            (string) $datos['idempotency_key'],
            (string) $datos['relacion'],
            (string) $datos['nombre_quien_retira'],
            (string) $datos['metodo_validacion'],
            $request->file('firma'),
            isset($datos['observaciones']) ? (string) $datos['observaciones'] : null,
            $request->file('evidencias', []) ?? [],
            isset($datos['bulto_ids']) ? array_map('intval', $datos['bulto_ids']) : null,
        );

        $entrega = $resguardo->entregas->sortByDesc('id')->first();

        return response()->json([
            'resguardo' => [
                'id' => $resguardo->id,
                'estado' => $resguardo->estado,
                'estado_etiqueta' => EtiquetasResguardoPdv::etiquetaEstado($resguardo->estado),
                'version' => $resguardo->version,
                'entrega_completada_at' => $resguardo->entrega_completada_at?->toIso8601String(),
                'bultos' => $resguardo->bultos->map(fn ($bulto) => [
                    'id' => $bulto->id,
                    'folio' => $bulto->folio,
                    'tipo' => $bulto->tipo,
                    'estado' => $bulto->estado,
                    'entrega_at' => $bulto->entrega_at?->toIso8601String(),
                ])->values()->all(),
            ],
            'entrega' => $entrega ? [
                'id' => $entrega->id,
                'relacion' => $entrega->relacion,
                'nombre_quien_retira' => $entrega->nombre_quien_retira,
                'entregado_at' => $entrega->entregado_at?->toIso8601String(),
                'integracion_cp' => $entrega->snapshot_json['integracion_cp'] ?? null,
            ] : null,
        ]);
    }
}
