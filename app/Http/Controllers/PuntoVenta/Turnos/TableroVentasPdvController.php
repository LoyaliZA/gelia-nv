<?php

namespace App\Http\Controllers\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Turnos\ConsultarTableroVentasPdvRequest;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Turnos\ConsultaTableroVentasPdvService;
use App\Support\PuntoVenta\Turnos\MotivosCierreAtencionTurnoPdv;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class TableroVentasPdvController extends Controller
{
    public function index(
        ConsultarTableroVentasPdvRequest $request,
        ConsultaTableroVentasPdvService $consulta,
        ResuelveAlcancePdv $alcance,
    ): Response|JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $ahora = now();
        $payload = $consulta->payload($user, $ahora);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('PuntoVenta/Turnos/Ventas', [
            'tablero' => fn () => $payload,
            'permisos' => fn () => $this->serializarPermisos($user, $alcance),
            'sucursal_activa' => fn () => $this->serializarSucursalActiva($user, $alcance),
            'sucursales_asignadas' => fn () => $this->serializarSucursalesAsignadas($user),
            'catalogos' => fn () => [
                'servicio' => 'Ventas',
                'motivos_cierre' => $this->catalogoMotivosCierre(),
            ],
        ]);
    }

    public function datos(
        ConsultarTableroVentasPdvRequest $request,
        ConsultaTableroVentasPdvService $consulta,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json($consulta->payload($user, now()));
    }

    /**
     * @return array<string, bool>
     */
    private function serializarPermisos(User $user, ResuelveAlcancePdv $alcance): array
    {
        return [
            'ver' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_VER),
            'cerrar_atencion' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION),
            'transferir' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_TRANSFERIR),
        ];
    }

    /**
     * @return list<array{valor: string, etiqueta: string}>
     */
    private function catalogoMotivosCierre(): array
    {
        return [
            ['valor' => MotivosCierreAtencionTurnoPdv::VENTA, 'etiqueta' => 'Venta'],
            ['valor' => MotivosCierreAtencionTurnoPdv::SIN_VENTA, 'etiqueta' => 'Sin venta'],
            ['valor' => MotivosCierreAtencionTurnoPdv::NO_SE_PRESENTO, 'etiqueta' => 'No se presentó'],
            ['valor' => MotivosCierreAtencionTurnoPdv::OTRO, 'etiqueta' => 'Otro'],
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
