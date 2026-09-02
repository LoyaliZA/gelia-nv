<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\CorregirResguardoPdvRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\CorregirResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;

class CorregirResguardoPdvController extends Controller
{
    public function __invoke(
        CorregirResguardoPdvRequest $request,
        ResguardoPdv $resguardo,
        CorregirResguardoPdvService $corregir,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $datos = $request->payloadOperacion();

        $resguardo = $corregir->ejecutar(
            $resguardo,
            $user,
            (int) $datos['version'],
            (string) $datos['idempotency_key'],
            (string) $datos['tipo_correccion'],
            (string) $datos['motivo'],
            $request->datosCorreccion(),
            $request->file('evidencias', []) ?? [],
        );

        $evento = $resguardo->eventos()
            ->where('tipo_evento', ResguardoPdvEvento::TIPO_CORRECCION_ADMINISTRATIVA)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'resguardo' => [
                'id' => $resguardo->id,
                'estado' => $resguardo->estado,
                'estado_etiqueta' => EtiquetasResguardoPdv::etiquetaEstado($resguardo->estado),
                'version' => $resguardo->version,
                'snapshot_folio' => $resguardo->snapshot_folio,
                'snapshot_cliente_nombre' => $resguardo->snapshot_cliente_nombre,
            ],
            'evento' => $evento ? [
                'id' => $evento->id,
                'tipo_evento' => $evento->tipo_evento,
                'snapshot_json' => $evento->snapshot_json,
            ] : null,
        ]);
    }
}
