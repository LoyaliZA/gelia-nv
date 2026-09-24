<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\ConsultarBandejasResguardoPdvRequest;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\ConsultaBandejasResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\RegistroManualResguardoPdvConfig;
use App\Support\PuntoVenta\Resguardos\DepartamentosOrigenResguardoManualPdv;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class BandejaResguardoPdvController extends Controller
{
    public function index(
        ConsultarBandejasResguardoPdvRequest $request,
        ConsultaBandejasResguardoPdvService $consulta,
        ResuelveAlcancePdv $alcance,
        RegistroManualResguardoPdvConfig $registroManual,
    ): Response|JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $payload = $consulta->payload($user, $request->filtros());

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('PuntoVenta/Resguardos/Index', [
            'resguardos' => fn () => $payload['resguardos'],
            'metricas' => fn () => $payload['metricas'],
            'filtros' => $payload['filtros'],
            'bandeja' => $payload['bandeja'],
            'catalogos' => fn () => [
                'bandejas' => EtiquetasResguardoPdv::bandejas(),
                'estados' => EtiquetasResguardoPdv::estados(),
                'antiguedades' => EtiquetasResguardoPdv::antiguedades(),
                'origenes_pedido' => $registroManual->estaActivo()
                    ? DepartamentosOrigenResguardoManualPdv::serializar()
                    : [],
            ],
            'permisos' => fn () => [
                'ver_vencidos' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER_VENCIDOS),
                'ver_rezagados' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER_REZAGADOS),
                'reponer_vencido' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_REPONER_VENCIDO),
                'recibir' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR_GERENTE),
                'confirmar_custodia' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_CUSTODIA),
                'entregar' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR),
            ],
            'sucursal_activa' => fn () => $this->serializarSucursalActiva($user, $alcance),
            'sucursales_asignadas' => fn () => $this->serializarSucursalesAsignadas($user),
            'operativa' => fn () => [
                'antiguedad_configurada' => $consulta->antiguedadConfigurada(),
                'registro_manual' => $registroManual->estaActivo(),
            ],
            'recepcion_modal_id' => fn () => $request->integer('recepcion') ?: null,
            'detalle_modal_id' => fn () => $request->integer('detalle') ?: null,
            'puede_ver_historial_entregas' => fn () => $alcance->tienePermisoPdv(
                $user,
                PuntoVentaModulo::PERMISO_RESGUARDOS_VER_HISTORIAL_ENTREGAS
            ),
        ]);
    }

    /**
     * @return array{id: int, nombre: string}|null
     */
    private function serializarSucursalActiva(User $user, ResuelveAlcancePdv $alcance): ?array
    {
        $activaId = $alcance->sucursalActivaId($user);
        if ($activaId === null) {
            return null;
        }

        $sucursal = Sucursal::query()->find($activaId, ['id', 'nombre']);
        if ($sucursal === null) {
            return null;
        }

        return [
            'id' => $sucursal->id,
            'nombre' => $sucursal->nombre,
        ];
    }

    /**
     * @return list<array{id: int, nombre: string}>
     */
    private function serializarSucursalesAsignadas(User $user): array
    {
        $user->loadMissing('sucursales');

        return $user->sucursales
            ->filter(static fn (Sucursal $sucursal): bool => $sucursal->activo && (bool) $sucursal->pivot->activo)
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(static fn (Sucursal $sucursal): array => [
                'id' => $sucursal->id,
                'nombre' => $sucursal->nombre,
            ])
            ->values()
            ->all();
    }

    public function listado(
        ConsultarBandejasResguardoPdvRequest $request,
        ConsultaBandejasResguardoPdvService $consulta,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $payload = $consulta->payload($user, $request->filtros());

        return response()->json([
            'bandeja' => $payload['bandeja'],
            'resguardos' => $payload['resguardos'],
            'metricas' => $payload['metricas'],
            'filtros' => $payload['filtros'],
        ]);
    }
}
