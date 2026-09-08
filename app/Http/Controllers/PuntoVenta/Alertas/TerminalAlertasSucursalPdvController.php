<?php

namespace App\Http\Controllers\PuntoVenta\Alertas;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Alertas\ActivarTerminalAlertasSucursalPdvRequest;
use App\Http\Requests\PuntoVenta\Alertas\LatidoTerminalAlertasSucursalPdvRequest;
use App\Http\Requests\PuntoVenta\Alertas\LiberarTerminalAlertasSucursalPdvRequest;
use App\Models\User;
use App\Services\PuntoVenta\Alertas\ActivarTerminalAlertasSucursalPdvService;
use App\Services\PuntoVenta\Alertas\ConsultarTerminalAlertasSucursalPdvService;
use App\Services\PuntoVenta\Alertas\LiberarTerminalAlertasSucursalPdvService;
use App\Services\PuntoVenta\Alertas\RenovarTerminalAlertasSucursalPdvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminalAlertasSucursalPdvController extends Controller
{
    public function estado(
        Request $request,
        ConsultarTerminalAlertasSucursalPdvService $consulta,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $sucursalId = (int) $request->integer('sucursal_id');
        $terminalId = $request->string('terminal_id')->toString();

        return response()->json(
            $consulta->estadoParaUsuario(
                $user,
                $sucursalId,
                $terminalId !== '' ? $terminalId : null,
                now(),
            ),
        );
    }

    public function activar(
        ActivarTerminalAlertasSucursalPdvRequest $request,
        ActivarTerminalAlertasSucursalPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json(
            $servicio->ejecutar(
                $user,
                (int) $request->integer('sucursal_id'),
                $request->input('terminal_id'),
                now(),
            ),
        );
    }

    public function latido(
        LatidoTerminalAlertasSucursalPdvRequest $request,
        RenovarTerminalAlertasSucursalPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json(
            $servicio->ejecutar(
                $user,
                (int) $request->integer('sucursal_id'),
                (string) $request->input('terminal_id'),
                now(),
            ),
        );
    }

    public function liberar(
        LiberarTerminalAlertasSucursalPdvRequest $request,
        LiberarTerminalAlertasSucursalPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $terminalId = $request->input('terminal_id');

        return response()->json(
            $servicio->ejecutar(
                $user,
                (int) $request->integer('sucursal_id'),
                is_string($terminalId) ? $terminalId : null,
                now(),
                (string) ($request->input('motivo') ?: 'manual'),
            ),
        );
    }
}
