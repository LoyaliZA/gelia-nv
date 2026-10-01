<?php

namespace App\Http\Controllers\Api\V1\Mobile\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\RegistroManualResguardoPdvConfig;
use App\Support\PuntoVenta\Resguardos\DepartamentosOrigenResguardoManualPdv;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContextoPuntoVentaMobileController extends Controller
{
    public function show(
        Request $request,
        AlcancePdv $alcance,
        RegistroManualResguardoPdvConfig $registroManual,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->payload($user, $alcance, $registroManual));
    }

    public function establecerSucursal(
        Request $request,
        AlcancePdv $alcance,
        RegistroManualResguardoPdvConfig $registroManual,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $datos = $request->validate([
            'sucursal_id' => ['required', 'integer'],
        ]);

        try {
            $alcance->establecerSucursalActiva($user, (int) $datos['sucursal_id']);
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->json($this->payload($user, $alcance, $registroManual));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        User $user,
        AlcancePdv $alcance,
        RegistroManualResguardoPdvConfig $registroManual,
    ): array {
        $user->loadMissing('sucursales');

        $operables = $user->sucursales
            ->filter(static fn (Sucursal $sucursal): bool => $sucursal->activo && (bool) $sucursal->pivot->activo)
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(static fn (Sucursal $sucursal): array => [
                'id' => $sucursal->id,
                'nombre' => $sucursal->nombre,
                'es_principal' => (bool) $sucursal->pivot->es_principal,
            ])
            ->values();

        $activaId = $alcance->sucursalActivaId($user);
        $registroManualActivo = $registroManual->estaActivo();

        return [
            'sucursal_activa' => $operables->firstWhere('id', $activaId),
            'sucursales_operables' => $operables->all(),
            'registro_manual' => $registroManualActivo,
            'origenes' => $registroManualActivo
                ? DepartamentosOrigenResguardoManualPdv::serializar()
                : [],
            'permisos' => [
                'resguardos_ver' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER),
                'resguardos_registrar_manual' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_REGISTRAR_MANUAL),
                'resguardos_confirmar_llegada' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_LLEGADA),
                'resguardos_enviar_a_custodia' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_ENVIAR_A_CUSTODIA),
                'resguardos_confirmar_custodia' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_CUSTODIA),
                'resguardos_entregar' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR),
                'resguardos_ver_rezagados' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER_REZAGADOS),
                'resguardos_ver_vencidos' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER_VENCIDOS),
                'resguardos_incidencia_folio' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FOLIO),
                'resguardos_incidencia_dano' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_DANO),
                'resguardos_incidencia_faltante' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FALTANTE),
                'resguardos_autorizar_entrega_incidencia' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_AUTORIZAR_ENTREGA_INCIDENCIA),
                'resguardos_confirmar_devolucion' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_DEVOLUCION),
                'resguardos_reponer_vencido' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_REPONER_VENCIDO),
                'resguardos_ver_historial_entregas' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER_HISTORIAL_ENTREGAS),
                'turnos_ver' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_VER),
                'turnos_alta' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_ALTA),
            ],
        ];
    }
}
