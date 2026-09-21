<?php

namespace App\Http\Controllers\PuntoVenta\Pantallas;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Pantallas\GestionarEnlacePantallaSalaPdvRequest;
use App\Http\Requests\PuntoVenta\Pantallas\GestionarPublicidadPantallaSalaPdvRequest;
use App\Models\PuntoVenta\PdvPantallaPublicidad;
use App\Models\User;
use App\Services\PuntoVenta\Pantallas\GestionarPublicidadPantallaSalaPdvService;
use Illuminate\Http\JsonResponse;

class PublicidadPantallaSalaPdvController extends Controller
{
    public function index(
        GestionarEnlacePantallaSalaPdvRequest $request,
        GestionarPublicidadPantallaSalaPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $sucursalId = (int) $request->integer('sucursal_id');

        return response()->json([
            'items' => $servicio->listar($user, $sucursalId),
        ]);
    }

    public function store(
        GestionarPublicidadPantallaSalaPdvRequest $request,
        GestionarPublicidadPantallaSalaPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'items' => $servicio->crear(
                $user,
                (int) $request->integer('sucursal_id'),
                $request->validated(),
                $request->file('archivo'),
            ),
        ], 201);
    }

    public function update(
        GestionarPublicidadPantallaSalaPdvRequest $request,
        PdvPantallaPublicidad $publicidad,
        GestionarPublicidadPantallaSalaPdvService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'items' => $servicio->actualizar(
                $user,
                (int) $request->integer('sucursal_id'),
                (int) $publicidad->id,
                $request->validated(),
                $request->file('archivo'),
            ),
        ]);
    }

    public function destroy(
        GestionarEnlacePantallaSalaPdvRequest $request,
        PdvPantallaPublicidad $publicidad,
        GestionarPublicidadPantallaSalaPdvService $servicio,
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
}
