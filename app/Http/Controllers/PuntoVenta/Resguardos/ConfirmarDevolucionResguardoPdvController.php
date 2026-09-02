<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\ConfirmarDevolucionResguardoPdvRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\ConfirmarDevolucionResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;

class ConfirmarDevolucionResguardoPdvController extends Controller
{
    public function __invoke(
        ConfirmarDevolucionResguardoPdvRequest $request,
        ResguardoPdv $resguardo,
        ConfirmarDevolucionResguardoPdvService $confirmar,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $datos = $request->payloadOperacion();

        $resguardo = $confirmar->ejecutar(
            $resguardo,
            $user,
            (int) $datos['version'],
            (string) $datos['idempotency_key'],
            (string) $datos['motivo'],
            $request->file('evidencias', []) ?? [],
        );

        $evento = $resguardo->eventos()
            ->where('tipo_evento', ResguardoPdvEvento::TIPO_DEVOLUCION_CONFIRMADA)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'resguardo' => [
                'id' => $resguardo->id,
                'estado' => $resguardo->estado,
                'estado_etiqueta' => EtiquetasResguardoPdv::etiquetaEstado($resguardo->estado),
                'version' => $resguardo->version,
                'devolucion_confirmada_at' => $resguardo->devolucion_confirmada_at?->toIso8601String(),
                'bultos' => $resguardo->bultos->map(fn ($bulto) => [
                    'id' => $bulto->id,
                    'folio' => $bulto->folio,
                    'estado' => $bulto->estado,
                    'devolucion_salida_at' => $bulto->devolucion_salida_at?->toIso8601String(),
                ])->values()->all(),
            ],
            'evento' => $evento ? [
                'id' => $evento->id,
                'tipo_evento' => $evento->tipo_evento,
                'integracion_cp' => $evento->snapshot_json['integracion_cp'] ?? null,
            ] : null,
        ]);
    }
}
