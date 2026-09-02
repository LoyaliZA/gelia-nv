<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\ConsultarBandejasResguardoPdvRequest;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\ConsultaBandejasResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class BandejaResguardoPdvController extends Controller
{
    public function index(
        ConsultarBandejasResguardoPdvRequest $request,
        ConsultaBandejasResguardoPdvService $consulta,
        ResuelveAlcancePdv $alcance,
    ): Response|JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $payload = $consulta->payload($user, $request->filtros());

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('PuntoVenta/Resguardos/Index', [
            'resguardos' => fn () => $payload['resguardos'],
            'metricas' => fn () => $payload['metricas'],
            'filtros' => $payload['filtros'],
            'bandeja' => $payload['bandeja'],
            'catalogos' => fn () => [
                'bandejas' => EtiquetasResguardoPdv::bandejas(),
                'estados' => EtiquetasResguardoPdv::estados(),
                'antiguedades' => EtiquetasResguardoPdv::antiguedades(),
            ],
            'permisos' => fn () => [
                'ver_vencidos' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER_VENCIDOS),
                'recibir' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR),
                'entregar' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR),
            ],
        ]);
    }

    public function listado(
        ConsultarBandejasResguardoPdvRequest $request,
        ConsultaBandejasResguardoPdvService $consulta,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $payload = $consulta->payload($user, $request->filtros());

        return response()->json([
            'bandeja' => $payload['bandeja'],
            'resguardos' => $payload['resguardos'],
            'metricas' => $payload['metricas'],
            'filtros' => $payload['filtros'],
        ]);
    }
}
