<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarRecepcionFisicaPdvRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\RegistrarRecepcionFisicaPdvService;
use App\Support\PuntoVenta\Resguardos\EstadoRecepcionResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;

class RecepcionFisicaResguardoPdvController extends Controller
{
    public function __invoke(
        RegistrarRecepcionFisicaPdvRequest $request,
        ResguardoPdv $resguardo,
        RegistrarRecepcionFisicaPdvService $registrar,
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
                'recepcion_fisica_at' => $resguardo->recepcion_fisica_at?->toIso8601String(),
                'cantidad_bultos_esperada' => (int) $resguardo->cantidad_bultos_esperada,
                'cantidad_bultos_recibida' => EstadoRecepcionResguardoPdv::cantidadRecibida($resguardo),
                'cantidad_bultos_pendiente' => EstadoRecepcionResguardoPdv::cantidadPendiente($resguardo),
                'recepcion_completa' => EstadoRecepcionResguardoPdv::recepcionCompleta($resguardo),
                'almacen_id' => $resguardo->almacen_id,
                'bultos' => $resguardo->bultos->map(fn ($bulto) => [
                    'id' => $bulto->id,
                    'folio' => $bulto->folio,
                    'tipo' => $bulto->tipo,
                    'estado' => $bulto->estado,
                    'recepcion_at' => $bulto->recepcion_at?->toIso8601String(),
                ])->values()->all(),
            ],
        ]);
    }
}
