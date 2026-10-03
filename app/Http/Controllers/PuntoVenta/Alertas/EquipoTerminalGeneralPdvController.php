<?php

namespace App\Http\Controllers\PuntoVenta\Alertas;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Models\PuntoVenta\PdvTerminalAlertasSucursal;
use App\Models\User;
use App\Services\PuntoVenta\Alertas\ConsultarTerminalAlertasSucursalPdvService;
use App\Services\PuntoVenta\Operacion\ConsultaEquipoOperativoPdvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipoTerminalGeneralPdvController extends Controller
{
    public function index(
        Request $request,
        ConsultarTerminalAlertasSucursalPdvService $consulta,
        ConsultaEquipoOperativoPdvService $equipo,
        ResuelveAlcancePdv $alcance,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $terminalId = trim((string) $request->query('terminal_id', ''));
        $sucursalId = $alcance->sucursalActivaId($user);

        if ($sucursalId === null || $terminalId === '') {
            abort(403);
        }

        $estado = $consulta->estadoParaUsuario(
            $user,
            $sucursalId,
            $terminalId,
            now(),
            PdvTerminalAlertasSucursal::PROPOSITO_GENERAL,
        );

        if (($estado['estado'] ?? '') !== 'terminal_activa') {
            abort(403);
        }

        $personas = array_map(
            static fn (array $miembro): array => array_merge($miembro, ['acciones' => []]),
            $equipo->listar($sucursalId, now()),
        );

        return response()->json([
            'equipo' => $personas,
            'user_id' => $user->id,
        ]);
    }
}
