<?php

namespace App\Http\Controllers\Medios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Medios\CompletarCargaMedioRequest;
use App\Http\Requests\Medios\IniciarCargaMedioRequest;
use App\Http\Requests\Medios\PartesCargaMedioRequest;
use App\Models\Medios\MedioCarga;
use App\Models\User;
use App\Services\Medios\GestionarCargaMedioService;
use Illuminate\Http\JsonResponse;

class CargaMedioController extends Controller
{
    public function iniciar(IniciarCargaMedioRequest $request, GestionarCargaMedioService $servicio): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($servicio->iniciar($user, $request->validated()), 201);
    }

    public function partes(
        PartesCargaMedioRequest $request,
        MedioCarga $carga,
        GestionarCargaMedioService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json($servicio->urlsPartes(
            $user,
            (int) $carga->id,
            $request->validated('part_numbers'),
        ));
    }

    public function estado(MedioCarga $carga, GestionarCargaMedioService $servicio): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();

        return response()->json($servicio->estado($user, (int) $carga->id));
    }

    public function completar(
        CompletarCargaMedioRequest $request,
        MedioCarga $carga,
        GestionarCargaMedioService $servicio,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json($servicio->completar($user, (int) $carga->id, $request->validated()));
    }

    public function cancelar(MedioCarga $carga, GestionarCargaMedioService $servicio): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();
        $servicio->cancelar($user, (int) $carga->id);

        return response()->json(['ok' => true]);
    }
}
