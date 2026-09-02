<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\ConsultaDetalleResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DetalleResguardoPdvController extends Controller
{
    public function show(
        Request $request,
        ResguardoPdv $resguardo,
        ConsultaDetalleResguardoPdvService $consulta,
        ResuelveAlcancePdv $alcance,
    ): Response|JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $payload = $consulta->obtener($user, $resguardo);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('PuntoVenta/Resguardos/Show', [
            'resguardo' => $payload['resguardo'],
            'timeline' => $payload['timeline'],
            'catalogos' => fn () => [
                'estados' => EtiquetasResguardoPdv::estados(),
                'antiguedades' => EtiquetasResguardoPdv::antiguedades(),
                'eventos' => EtiquetasResguardoPdv::eventos(),
            ],
            'permisos' => fn () => [
                'recibir' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR),
                'entregar' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR),
            ],
        ]);
    }
}
