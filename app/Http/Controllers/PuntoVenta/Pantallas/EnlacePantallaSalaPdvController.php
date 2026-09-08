<?php

namespace App\Http\Controllers\PuntoVenta\Pantallas;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Pantallas\GestionarEnlacePantallaSalaPdvRequest;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\Pantallas\GestionarEnlacePantallaSalaPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EnlacePantallaSalaPdvController extends Controller
{
    public function index(
        Request $request,
        ResuelveAlcancePdv $alcance,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $alcance->asegurarConsultaPiso($user, PuntoVentaModulo::PERMISO_PANTALLA_SALA_ABRIR);

        return Inertia::render('PuntoVenta/Pantallas/Acceso', [
            'sucursal_activa' => fn () => $this->serializarSucursalActiva($user, $alcance),
            'sucursales_asignadas' => fn () => $this->serializarSucursalesAsignadas($user),
        ]);
    }

    public function estado(
        GestionarEnlacePantallaSalaPdvRequest $request,
        GestionarEnlacePantallaSalaPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json(
            $servicio->consultar($user, (int) $request->integer('sucursal_id'), now()),
        );
    }

    public function obtener(
        GestionarEnlacePantallaSalaPdvRequest $request,
        GestionarEnlacePantallaSalaPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json(
            $servicio->obtenerOGenerar(
                $user,
                (int) $request->integer('sucursal_id'),
                now(),
                $request->boolean('regenerar'),
            ),
        );
    }

    public function revocar(
        GestionarEnlacePantallaSalaPdvRequest $request,
        GestionarEnlacePantallaSalaPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json(
            $servicio->revocar($user, (int) $request->integer('sucursal_id'), now()),
        );
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
