<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarSalidaTerminalResguardoPdvRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\RegistrarSalidaTerminalResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;

class SalidaTerminalResguardoPdvController extends Controller
{
    public function __invoke(
        RegistrarSalidaTerminalResguardoPdvRequest $request,
        ResguardoPdv $resguardo,
        RegistrarSalidaTerminalResguardoPdvService $registrar,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $datos = $request->payloadOperacion();

        $resguardo = $registrar->ejecutar(
            $resguardo,
            $user,
            (int) $datos['version'],
            (string) $datos['idempotency_key'],
            (string) $datos['receptor'],
        );

        $salida = $resguardo->salidaTerminal();

        return response()->json([
            'resguardo' => [
                'id' => $resguardo->id,
                'estado' => $resguardo->estado,
                'estado_etiqueta' => EtiquetasResguardoPdv::etiquetaEstadoResguardo($resguardo),
                'version' => $resguardo->version,
                'entrega_completada_at' => $resguardo->entrega_completada_at?->toIso8601String(),
                'salida_terminal' => $salida ? [
                    'at' => $salida['at'] ?? null,
                    'receptor' => $salida['receptor'] ?? null,
                ] : null,
                'bultos' => $resguardo->bultos->map(fn ($bulto) => [
                    'id' => $bulto->id,
                    'folio' => $bulto->folio,
                    'tipo' => $bulto->tipo,
                    'estado' => $bulto->estado,
                    'entrega_at' => $bulto->entrega_at?->toIso8601String(),
                ])->values()->all(),
            ],
        ]);
    }
}
