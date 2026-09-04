<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarEntregaMultipleResguardoPdvRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\RegistrarEntregaMultipleResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;

class EntregaMultipleResguardoPdvController extends Controller
{
    public function __invoke(
        RegistrarEntregaMultipleResguardoPdvRequest $request,
        RegistrarEntregaMultipleResguardoPdvService $registrar,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $items = [];

        foreach ($request->payloadOperacion() as $datos) {
            $resguardo = ResguardoPdv::query()->findOrFail((int) $datos['resguardo_id']);
            $evidencias = $datos['evidencias'] ?? [];

            $items[] = [
                'resguardo' => $resguardo,
                'version' => (int) $datos['version'],
                'idempotency_key' => (string) $datos['idempotency_key'],
                'relacion' => (string) $datos['relacion'],
                'nombre_quien_retira' => (string) $datos['nombre_quien_retira'],
                'metodo_validacion' => (string) $datos['metodo_validacion'],
                'firma' => $datos['firma'],
                'observaciones' => isset($datos['observaciones']) ? (string) $datos['observaciones'] : null,
                'evidencias' => is_array($evidencias) ? $evidencias : [],
                'bulto_ids' => isset($datos['bulto_ids']) ? array_map('intval', $datos['bulto_ids']) : null,
            ];
        }

        $resguardos = $registrar->ejecutar($user, $items);

        return response()->json([
            'resguardos' => collect($resguardos)->map(fn (ResguardoPdv $resguardo) => [
                'id' => $resguardo->id,
                'estado' => $resguardo->estado,
                'estado_etiqueta' => EtiquetasResguardoPdv::etiquetaEstado($resguardo->estado),
                'version' => $resguardo->version,
                'entrega_completada_at' => $resguardo->entrega_completada_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }
}
