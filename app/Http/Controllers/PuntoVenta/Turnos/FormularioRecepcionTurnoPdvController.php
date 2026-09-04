<?php

namespace App\Http\Controllers\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Turnos\ConsultarBandejaRecepcionTurnoPdvRequest;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Turnos\ConsultaBandejaRecepcionTurnoPdvService;
use App\Support\PuntoVenta\Turnos\MotivosBajaColaTurnoPdv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FormularioRecepcionTurnoPdvController extends Controller
{
    public function show(
        Request $request,
        ResuelveAlcancePdv $alcance,
        ConsultaBandejaRecepcionTurnoPdvService $consulta,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $ahora = now();

        return Inertia::render('PuntoVenta/Turnos/Recepcion', [
            'bandeja' => fn () => $this->bandejaInicial($user, $consulta, $ahora),
            'permisos' => fn () => $this->serializarPermisos($user, $alcance),
            'sucursal_activa' => fn () => $this->serializarSucursalActiva($user, $alcance),
            'sucursales_asignadas' => fn () => $this->serializarSucursalesAsignadas($user),
            'catalogos' => fn () => $this->serializarCatalogos(),
        ]);
    }

    public function datos(
        ConsultarBandejaRecepcionTurnoPdvRequest $request,
        ConsultaBandejaRecepcionTurnoPdvService $consulta,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json($consulta->payload($user, now()));
    }

    /**
     * @return array<string, mixed>
     */
    private function bandejaInicial(
        User $user,
        ConsultaBandejaRecepcionTurnoPdvService $consulta,
        \Carbon\CarbonInterface $ahora,
    ): array {
        if (! app(ResuelveAlcancePdv::class)->permiteConsultaPiso($user, PuntoVentaModulo::PERMISO_TURNOS_VER)) {
            return [
                'servidor_at' => $ahora->toIso8601String(),
                'en_cola' => [],
                'asignados' => [],
            ];
        }

        return $consulta->payload($user, $ahora);
    }

    /**
     * @return array<string, bool>
     */
    private function serializarPermisos(User $user, ResuelveAlcancePdv $alcance): array
    {
        return [
            'ver' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_VER),
            'alta' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_ALTA),
            'marcar_prioridad' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_MARCAR_PRIORIDAD),
            'baja_cola' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_BAJA_COLA),
        ];
    }

    /**
     * @return array{
     *     servicio: string,
     *     estados: array<string, string>,
     *     motivos_baja: list<array{valor: string, etiqueta: string}>
     * }
     */
    private function serializarCatalogos(): array
    {
        return [
            'servicio' => 'Ventas',
            'estados' => [
                TurnoPdv::ESTADO_EN_COLA => 'En cola',
                TurnoPdv::ESTADO_ASIGNADO => 'Asignado',
            ],
            'motivos_baja' => [
                ['valor' => MotivosBajaColaTurnoPdv::SE_FUE, 'etiqueta' => 'Se fue'],
                ['valor' => MotivosBajaColaTurnoPdv::DESISTIO, 'etiqueta' => 'Desistió'],
                ['valor' => MotivosBajaColaTurnoPdv::OTRO, 'etiqueta' => 'Otro'],
            ],
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
