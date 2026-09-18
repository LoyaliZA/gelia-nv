<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\ConsultaFormularioRecepcionFisicaPdvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormularioRecepcionFisicaResguardoPdvController extends Controller
{
    public function show(
        Request $request,
        ResguardoPdv $resguardo,
        ConsultaFormularioRecepcionFisicaPdvService $consulta,
    ): JsonResponse|RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $payload = $consulta->obtener($user, $resguardo);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return redirect()->route('punto_venta.resguardos.index', [
            'bandeja' => 'por_recibir',
            'recepcion' => $resguardo->id,
        ]);
    }
}
