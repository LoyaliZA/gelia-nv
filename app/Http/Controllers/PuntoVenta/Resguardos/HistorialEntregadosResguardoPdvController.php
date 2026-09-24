<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\ConsultarHistorialEntregadosResguardoPdvRequest;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\ConsultaHistorialEntregadosResguardoPdvService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class HistorialEntregadosResguardoPdvController extends Controller
{
    public function index(
        ConsultarHistorialEntregadosResguardoPdvRequest $request,
        ConsultaHistorialEntregadosResguardoPdvService $consulta,
        ResuelveAlcancePdv $alcance,
    ): Response|JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $payload = $consulta->payload($user, $request->filtros());

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('PuntoVenta/Resguardos/Entregados', [
            'resguardos' => fn () => $payload['resguardos'],
            'filtros' => $payload['filtros'],
            'sucursal_activa' => fn () => $this->serializarSucursalActiva($user, $alcance),
            'sucursales_asignadas' => fn () => $this->serializarSucursalesAsignadas($user),
            'puede_ver_bandeja' => fn () => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER),
            'detalle_modal_id' => fn () => $request->integer('detalle') ?: null,
        ]);
    }

    public function listado(
        ConsultarHistorialEntregadosResguardoPdvRequest $request,
        ConsultaHistorialEntregadosResguardoPdvService $consulta,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $payload = $consulta->payload($user, $request->filtros());

        return response()->json($payload);
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
}
