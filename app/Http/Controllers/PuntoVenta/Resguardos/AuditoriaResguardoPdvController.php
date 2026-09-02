<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\ConsultarAuditoriaResguardoPdvRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\ConsultaAuditoriaResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;

class AuditoriaResguardoPdvController extends Controller
{
    public function __invoke(
        ConsultarAuditoriaResguardoPdvRequest $request,
        ResguardoPdv $resguardo,
        ConsultaAuditoriaResguardoPdvService $consulta,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $payload = $consulta->obtener($user, $resguardo, $request->filtros());

        return response()->json([
            ...$payload,
            'catalogos' => [
                'eventos' => EtiquetasResguardoPdv::eventos(),
                'categorias' => [
                    'recepcion' => 'Recepción',
                    'incidencia' => 'Incidencias',
                    'entrega' => 'Entregas',
                    'devolucion' => 'Devoluciones',
                    'correccion' => 'Correcciones',
                    'sistema' => 'Sistema',
                    'integracion' => 'Integración CP',
                    'operacion' => 'Operación',
                ],
            ],
        ]);
    }
}
