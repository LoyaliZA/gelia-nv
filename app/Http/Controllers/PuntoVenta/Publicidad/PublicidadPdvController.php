<?php

namespace App\Http\Controllers\PuntoVenta\Publicidad;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Pantallas\GestionarEnlacePantallaSalaPdvRequest;
use App\Http\Requests\PuntoVenta\Publicidad\GestionarPublicidadPdvRequest;
use App\Http\Requests\PuntoVenta\Publicidad\OrdenarPublicidadPdvRequest;
use App\Models\PuntoVenta\PdvPantallaPublicidad;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\Publicidad\GestionarPublicidadPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicidadPdvController extends Controller
{
    public function index(Request $request, ResuelveAlcancePdv $alcance): Response
    {
        /** @var User $user */
        $user = $request->user();
        $alcance->asegurarConsultaPiso($user, PuntoVentaModulo::PERMISO_PUBLICIDAD_VER);

        return Inertia::render('PuntoVenta/Publicidad/Index', [
            'sucursal_activa' => fn () => $this->serializarSucursalActiva($user, $alcance),
            'sucursales_asignadas' => fn () => $this->serializarSucursalesAsignadas($user),
            'permisos' => [
                'crear' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_PUBLICIDAD_CREAR),
                'editar' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_PUBLICIDAD_EDITAR),
                'eliminar' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_PUBLICIDAD_ELIMINAR),
                'ordenar' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_PUBLICIDAD_ORDENAR),
            ],
        ]);
    }

    public function listar(
        GestionarEnlacePantallaSalaPdvRequest $request,
        GestionarPublicidadPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'items' => $servicio->listar($user, (int) $request->integer('sucursal_id')),
        ]);
    }

    public function store(
        GestionarPublicidadPdvRequest $request,
        GestionarPublicidadPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'items' => $servicio->crear($user, (int) $request->integer('sucursal_id'), $request->validated()),
        ], 201);
    }

    public function update(
        GestionarPublicidadPdvRequest $request,
        PdvPantallaPublicidad $publicidad,
        GestionarPublicidadPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'items' => $servicio->actualizar(
                $user,
                (int) $request->integer('sucursal_id'),
                (int) $publicidad->id,
                $request->validated(),
            ),
        ]);
    }

    public function ordenar(
        OrdenarPublicidadPdvRequest $request,
        GestionarPublicidadPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'items' => $servicio->ordenar(
                $user,
                (int) $request->integer('sucursal_id'),
                $request->validated('ids'),
            ),
        ]);
    }

    public function destroy(
        GestionarEnlacePantallaSalaPdvRequest $request,
        PdvPantallaPublicidad $publicidad,
        GestionarPublicidadPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'items' => $servicio->eliminar(
                $user,
                (int) $request->integer('sucursal_id'),
                (int) $publicidad->id,
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
}
