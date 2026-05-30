<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ApiExterna\ApiAplicacionService;
use App\Services\ApiExterna\ApiAuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthTokenController extends Controller
{
    public function __construct(
        protected ApiAplicacionService $aplicacionService,
        protected ApiAuditoriaService $auditoriaService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $this->auditoriaService->iniciarRequest($request);

        $validated = $request->validate([
            'client_id' => 'required|string|max:64',
            'client_secret' => 'required|string|max:128',
        ]);

        $aplicacion = $this->aplicacionService->validarCredenciales(
            $validated['client_id'],
            $validated['client_secret']
        );

        if (!$aplicacion) {
            $this->auditoriaService->registrar($request, null, 401, 'Credenciales inválidas');

            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        if (!$aplicacion->ipPermitida($request->ip())) {
            $this->auditoriaService->registrar($request, $aplicacion->id, 403, 'IP no permitida');

            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $token = $this->aplicacionService->emitirToken($aplicacion);

        $response = response()->json($token, 200);
        $this->auditoriaService->registrar($request, $aplicacion->id, 200);

        return $response;
    }
}
