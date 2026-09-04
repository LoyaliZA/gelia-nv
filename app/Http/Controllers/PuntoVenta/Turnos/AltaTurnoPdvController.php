<?php

namespace App\Http\Controllers\PuntoVenta\Turnos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Turnos\AltaTurnoPdvRequest;
use App\Models\User;
use App\Services\PuntoVenta\Turnos\AltaTurnoPdvService;
use Illuminate\Http\JsonResponse;

class AltaTurnoPdvController extends Controller
{
    public function __invoke(
        AltaTurnoPdvRequest $request,
        AltaTurnoPdvService $alta,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $datos = $request->payloadOperacion();

        $turno = $alta->ejecutar(
            $user,
            $datos['idempotency_key'],
            $datos['cliente_id'],
            $datos['nombre_llamado'],
            $datos['prioridad_adulto_mayor'],
            $datos['prioridad_discapacidad'],
        );

        return response()->json([
            'turno' => [
                'id' => $turno->id,
                'folio' => $turno->folio,
                'estado' => $turno->estado,
                'servicio' => $turno->servicio,
                'origen' => $turno->origen,
                'sucursal_id' => $turno->sucursal_id,
                'cliente_id' => $turno->cliente_id,
                'snapshot_nombre_llamado' => $turno->snapshot_nombre_llamado,
                'prioridad_adulto_mayor' => $turno->prioridad_adulto_mayor,
                'prioridad_discapacidad' => $turno->prioridad_discapacidad,
                'prioridad_diamante' => $turno->prioridad_diamante,
                'prioridad_vip' => $turno->prioridad_vip,
                'alta_at' => $turno->alta_at?->toIso8601String(),
                'alta_por_id' => $turno->alta_por_id,
                'version' => $turno->version,
            ],
        ], 201);
    }
}
