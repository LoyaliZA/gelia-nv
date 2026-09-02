<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\ConsultaFormularioRecepcionFisicaPdvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FormularioRecepcionFisicaResguardoPdvController extends Controller
{
    public function show(
        Request $request,
        ResguardoPdv $resguardo,
        ConsultaFormularioRecepcionFisicaPdvService $consulta,
    ): Response|JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $payload = $consulta->obtener($user, $resguardo);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('PuntoVenta/Resguardos/Recepcion', $payload);
    }
}
