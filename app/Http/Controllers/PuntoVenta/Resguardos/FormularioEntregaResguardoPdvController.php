<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\ConsultaFormularioEntregaResguardoPdvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FormularioEntregaResguardoPdvController extends Controller
{
    public function show(
        Request $request,
        ResguardoPdv $resguardo,
        ConsultaFormularioEntregaResguardoPdvService $consulta,
    ): Response|JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $payload = $consulta->obtener($user, $resguardo);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('PuntoVenta/Resguardos/Entrega', $payload);
    }
}
