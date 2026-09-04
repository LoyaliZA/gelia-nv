<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\ConsultaFormularioEntregaMultipleResguardoPdvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FormularioEntregaMultipleResguardoPdvController extends Controller
{
    public function show(
        Request $request,
        ConsultaFormularioEntregaMultipleResguardoPdvService $consulta,
    ): Response|JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $ids = $request->query('ids', []);
        if (! is_array($ids)) {
            $ids = array_filter(explode(',', (string) $ids));
        }

        $payload = $consulta->obtener($user, $ids);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('PuntoVenta/Resguardos/EntregaMultiple', $payload);
    }
}
