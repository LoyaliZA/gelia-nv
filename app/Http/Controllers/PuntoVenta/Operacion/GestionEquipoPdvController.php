<?php

namespace App\Http\Controllers\PuntoVenta\Operacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Operacion\DesactivarEquipoPdvRequest;
use App\Http\Requests\PuntoVenta\Operacion\GestionEquipoPdvRequest;
use App\Http\Requests\PuntoVenta\Operacion\IniciarPausaEquipoPdvRequest;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\ConsultaEstadoOperativoPdvService;
use App\Services\PuntoVenta\Operacion\GestionarEquipoOperativoPdvService;
use Illuminate\Http\JsonResponse;

class GestionEquipoPdvController extends Controller
{
    public function activar(
        GestionEquipoPdvRequest $request,
        User $user,
        GestionarEquipoOperativoPdvService $servicio,
        ConsultaEstadoOperativoPdvService $consulta,
    ): JsonResponse {
        /** @var User $gerente */
        $gerente = $request->user();

        $servicio->activar($gerente, $user, now(), $request->header('Idempotency-Key'));

        return response()->json($consulta->ejecutar($gerente, now()));
    }

    public function reactivar(
        GestionEquipoPdvRequest $request,
        User $user,
        GestionarEquipoOperativoPdvService $servicio,
        ConsultaEstadoOperativoPdvService $consulta,
    ): JsonResponse {
        /** @var User $gerente */
        $gerente = $request->user();

        $servicio->reactivar($gerente, $user, now(), $request->header('Idempotency-Key'));

        return response()->json($consulta->ejecutar($gerente, now()));
    }

    public function marcarNoLlego(
        GestionEquipoPdvRequest $request,
        User $user,
        GestionarEquipoOperativoPdvService $servicio,
        ConsultaEstadoOperativoPdvService $consulta,
    ): JsonResponse {
        /** @var User $gerente */
        $gerente = $request->user();

        $servicio->marcarNoLlego($gerente, $user, now());

        return response()->json($consulta->ejecutar($gerente, now()));
    }

    public function desactivar(
        DesactivarEquipoPdvRequest $request,
        User $user,
        GestionarEquipoOperativoPdvService $servicio,
        ConsultaEstadoOperativoPdvService $consulta,
    ): JsonResponse {
        /** @var User $gerente */
        $gerente = $request->user();

        $servicio->desactivar(
            $gerente,
            $user,
            (int) $request->validated('version'),
            now(),
            $request->header('Idempotency-Key'),
        );

        return response()->json($consulta->ejecutar($gerente, now()));
    }

    public function iniciarPausa(
        IniciarPausaEquipoPdvRequest $request,
        User $user,
        GestionarEquipoOperativoPdvService $servicio,
        ConsultaEstadoOperativoPdvService $consulta,
    ): JsonResponse {
        /** @var User $gerente */
        $gerente = $request->user();
        $datos = $request->validated();

        $servicio->iniciarPausa(
            $gerente,
            $user,
            (int) $datos['motivo_pausa_id'],
            $datos['motivo_detalle'] ?? null,
            now(),
            $request->header('Idempotency-Key'),
        );

        return response()->json($consulta->ejecutar($gerente, now()));
    }

    public function finalizarPausa(
        GestionEquipoPdvRequest $request,
        User $user,
        GestionarEquipoOperativoPdvService $servicio,
        ConsultaEstadoOperativoPdvService $consulta,
    ): JsonResponse {
        /** @var User $gerente */
        $gerente = $request->user();

        $servicio->finalizarPausa($gerente, $user, now(), $request->header('Idempotency-Key'));

        return response()->json($consulta->ejecutar($gerente, now()));
    }

    public function cerrarJornada(
        DesactivarEquipoPdvRequest $request,
        User $user,
        GestionarEquipoOperativoPdvService $servicio,
        ConsultaEstadoOperativoPdvService $consulta,
    ): JsonResponse {
        /** @var User $gerente */
        $gerente = $request->user();

        $servicio->cerrarJornada(
            $gerente,
            $user,
            (int) $request->validated('version'),
            now(),
            $request->header('Idempotency-Key'),
        );

        return response()->json($consulta->ejecutar($gerente, now()));
    }

    public function cancelarCierrePendiente(
        DesactivarEquipoPdvRequest $request,
        User $user,
        GestionarEquipoOperativoPdvService $servicio,
        ConsultaEstadoOperativoPdvService $consulta,
    ): JsonResponse {
        /** @var User $gerente */
        $gerente = $request->user();

        $servicio->cancelarCierrePendiente(
            $gerente,
            $user,
            (int) $request->validated('version'),
            now(),
            $request->header('Idempotency-Key'),
        );

        return response()->json($consulta->ejecutar($gerente, now()));
    }
}
