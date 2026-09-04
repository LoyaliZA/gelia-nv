<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\ReponerVencidoResguardoPdvRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\ReponerVencidoResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;

class ReponerVencidoResguardoPdvController extends Controller
{
    public function __invoke(
        ReponerVencidoResguardoPdvRequest $request,
        ResguardoPdv $resguardo,
        ReponerVencidoResguardoPdvService $reponer,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $datos = $request->payloadOperacion();

        $resguardo = $reponer->ejecutar(
            $resguardo,
            $user,
            (int) $datos['version'],
            (string) $datos['idempotency_key'],
            (string) $datos['motivo'],
        );

        $evento = $resguardo->eventos()
            ->where('tipo_evento', ResguardoPdvEvento::TIPO_VENCIDO_REPUESTO)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'resguardo' => [
                'id' => $resguardo->id,
                'estado' => $resguardo->estado,
                'estado_etiqueta' => EtiquetasResguardoPdv::etiquetaEstado($resguardo->estado),
                'version' => $resguardo->version,
                'vencido_repuesto_at' => $resguardo->vencido_repuesto_at?->toIso8601String(),
                'recepcion_fisica_at' => $resguardo->recepcion_fisica_at?->toIso8601String(),
            ],
            'evento' => $evento ? [
                'id' => $evento->id,
                'tipo_evento' => $evento->tipo_evento,
                'actor_id' => $evento->actor_id,
                'ocurrido_at' => $evento->ocurrido_at?->toIso8601String(),
                'motivo' => $evento->snapshot_json['motivo'] ?? null,
            ] : null,
        ]);
    }
}
