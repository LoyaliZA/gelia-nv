<?php

namespace App\Http\Controllers\Api\V1\Mobile\Traspasos;

use App\Http\Controllers\Controller;
use App\Models\SolicitudTraspaso;
use App\Models\User;
use App\Services\Mobile\Serializar\OperacionMovilSerializer;
use App\Services\Traspasos\ListarSolicitudesTraspasoService;
use App\Support\Api\V1\AutorizacionMovil;
use App\Support\Api\V1\RespuestaPaginada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TraspasoMobileController extends Controller
{
    public function index(Request $request, ListarSolicitudesTraspasoService $listar, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['traspasos.ver_listado']);
        $usuario->loadMissing('departamentos');

        $paginado = $listar->ejecutar(
            $usuario,
            $request->only(['q', 'tab', 'vendedor_id', 'almacen_origen_id', 'fecha_inicio', 'fecha_fin', 'fecha_entrega']),
            true,
            RespuestaPaginada::porPagina($request),
        );

        return response()->json([
            'data' => $paginado->getCollection()->map(fn (SolicitudTraspaso $traspaso) => $serializar->traspaso($traspaso))->values(),
            'meta' => RespuestaPaginada::meta($paginado),
            'metricas' => $listar->metricas($usuario),
        ]);
    }

    public function show(Request $request, SolicitudTraspaso $traspaso, ListarSolicitudesTraspasoService $listar, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['traspasos.ver_listado']);
        $usuario->loadMissing('departamentos');
        abort_unless($listar->usuarioPuedeVer($usuario, $traspaso), 404, 'Traspaso no encontrado.');

        $traspaso->load([
            'vendedor', 'departamento', 'sucursalSolicitante', 'estado', 'cliente', 'almacenOrigen', 'productos',
        ]);

        return response()->json([
            'data' => $serializar->traspaso($traspaso, true),
        ]);
    }

    private function usuario(Request $request): User
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $usuario;
    }
}
