<?php

namespace App\Http\Controllers\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Operacion\ConsultaEstadoOperativoPdvRequest;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\ConsultaEstadoOperativoPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class OperacionPdvController extends Controller
{
    public function index(
        ConsultaEstadoOperativoPdvRequest $request,
        ConsultaEstadoOperativoPdvService $consulta,
        ResuelveAlcancePdv $alcance,
    ): Response|JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $payload = $consulta->ejecutar($user, now());

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('PuntoVenta/Operacion/Index', [
            'estado' => fn () => $payload,
            'permisos' => fn () => $this->serializarPermisos($user, $alcance),
            'sucursal_activa' => fn () => $this->serializarSucursalActiva($user, $alcance),
            'sucursales_asignadas' => fn () => $this->serializarSucursalesAsignadas($user),
        ]);
    }

    public function datos(
        ConsultaEstadoOperativoPdvRequest $request,
        ConsultaEstadoOperativoPdvService $consulta,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json($consulta->ejecutar($user, now()));
    }

    /**
     * @return array<string, bool>
     */
    private function serializarPermisos(User $user, ResuelveAlcancePdv $alcance): array
    {
        return [
            'ver' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_VER),
            'jornada_abrir' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_OPERACION_JORNADA_ABRIR),
            'jornada_cerrar' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR),
            'pausa' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_OPERACION_PAUSA),
            'cerrar_sucursal' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR_SUCURSAL),
            'ampliar' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_OPERACION_JORNADA_AMPLIAR),
        ];
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
