<?php

namespace App\Http\Controllers;

use App\Services\Deploy\RegistrarAuditoriaDespliegueService;
use App\Support\Deploy\CalcularVersionesDeploy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeployVersionController extends Controller
{
    public function __invoke(
        Request $request,
        RegistrarAuditoriaDespliegueService $auditoria,
    ): JsonResponse {
        $versiones = CalcularVersionesDeploy::actual();
        try {
            $auditoria->registrarConsulta($versiones, $request->user()?->id);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()
            ->json([
                'version' => $versiones['shell'],
                'shell' => $versiones['shell'],
                'surfaces' => $versiones['surfaces'],
            ])
            ->header('Cache-Control', 'no-store');
    }
}
