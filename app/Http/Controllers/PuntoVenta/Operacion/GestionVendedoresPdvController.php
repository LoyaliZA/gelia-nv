<?php

namespace App\Http\Controllers\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Operacion\ConsultaGestionVendedoresPdvRequest;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\ConsultaGestionVendedoresPdvService;
use App\Services\PuntoVenta\Operacion\ConsultaMotivosPausaPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\SerializarCapacidadesPdvService;
use App\Services\PuntoVenta\Turnos\ConsultaBandejaReatencionPdvService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class GestionVendedoresPdvController extends Controller
{
    public function index(
        ConsultaGestionVendedoresPdvRequest $request,
        ConsultaGestionVendedoresPdvService $consulta,
        ConsultaMotivosPausaPdvService $motivosPausa,
        ConsultaBandejaReatencionPdvService $bandejaReatencion,
        ResuelveAlcancePdv $alcance,
        SerializarCapacidadesPdvService $capacidades,
    ): Response|JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $ahora = now();
        $payload = $consulta->ejecutar($user, $ahora);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('PuntoVenta/Operacion/GestionVendedores', [
            'estado' => fn () => $payload,
            'reatencion' => fn () => $bandejaReatencion->listar($user, $ahora),
            'motivos_pausa' => fn () => $motivosPausa->listarActivos(),
            'permisos' => fn () => $this->serializarPermisos($user, $alcance),
            'capacidades' => fn () => $capacidades->serializar($user),
            'sucursal_activa' => fn () => $this->serializarSucursalActiva($user, $alcance),
            'sucursales_asignadas' => fn () => $this->serializarSucursalesAsignadas($user),
        ]);
    }

    public function datos(
        ConsultaGestionVendedoresPdvRequest $request,
        ConsultaGestionVendedoresPdvService $consulta,
        ConsultaMotivosPausaPdvService $motivosPausa,
        ConsultaBandejaReatencionPdvService $bandejaReatencion,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $ahora = now();

        return response()->json(array_merge(
            $consulta->ejecutar($user, $ahora),
            [
                'motivos_pausa' => $motivosPausa->listarActivos(),
                'reatencion' => $bandejaReatencion->listar($user, $ahora),
            ],
        ));
    }

    /**
     * @return array<string, bool>
     */
    private function serializarPermisos(User $user, ResuelveAlcancePdv $alcance): array
    {
        return [
            'equipo_ver' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER),
            'equipo_gestionar' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR),
            'reatencion_asignar' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_REATENCION_ASIGNAR),
            'pantalla_sala_abrir' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_PANTALLA_SALA_ABRIR),
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
