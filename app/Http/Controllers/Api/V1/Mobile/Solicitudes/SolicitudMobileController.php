<?php

namespace App\Http\Controllers\Api\V1\Mobile\Solicitudes;

use App\Http\Controllers\Controller;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\Mobile\Serializar\OperacionMovilSerializer;
use App\Services\Solicitudes\ListarSolicitudesService;
use App\Support\Api\V1\AutorizacionMovil;
use App\Support\Api\V1\RespuestaPaginada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SolicitudMobileController extends Controller
{
    public function index(Request $request, ListarSolicitudesService $listar, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['solicitudes.ver_listado']);

        $filtros = $request->only(['q', 'tab', 'estado_id', 'proceso_id', 'fecha_inicio', 'fecha_fin']);
        $paginado = $listar->ejecutar($usuario, $filtros, true, RespuestaPaginada::porPagina($request));

        return response()->json([
            'data' => $paginado->getCollection()->map(fn (SolicitudTag $solicitud) => $serializar->solicitud($solicitud))->values(),
            'meta' => RespuestaPaginada::meta($paginado),
            'metricas' => $listar->metricas($usuario),
        ]);
    }

    public function show(Request $request, SolicitudTag $solicitud, ListarSolicitudesService $listar, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['solicitudes.ver_listado']);
        abort_unless($listar->visiblePara($usuario, $solicitud), 404, 'Solicitud no encontrada.');

        $solicitud->load(['cliente', 'vendedor', 'proceso', 'estado', 'departamento']);

        return response()->json([
            'data' => $serializar->solicitud($solicitud),
        ]);
    }

    private function usuario(Request $request): User
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $usuario;
    }
}
