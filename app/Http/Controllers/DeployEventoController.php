<?php

namespace App\Http\Controllers;

use App\Services\Deploy\RegistrarAuditoriaDespliegueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeployEventoController extends Controller
{
    public function __invoke(
        Request $request,
        RegistrarAuditoriaDespliegueService $auditoria,
    ): JsonResponse {
        $datos = $request->validate([
            'accion' => ['required', 'in:recarga_iniciada,recarga_en_espera,consulta_fallida'],
            'superficie' => ['required', 'in:sala,turnos'],
            'version' => ['nullable', 'string', 'max:64'],
            'detalle' => ['nullable', 'string', 'max:300'],
        ]);

        $auditoria->registrarEventoCliente(
            $datos['accion'],
            $datos['superficie'],
            $datos['version'] ?? null,
            $datos['detalle'] ?? null,
            $request->user()?->id,
        );

        return response()->json(['ok' => true])->header('Cache-Control', 'no-store');
    }
}
