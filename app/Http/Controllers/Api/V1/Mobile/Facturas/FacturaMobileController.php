<?php

namespace App\Http\Controllers\Api\V1\Mobile\Facturas;

use App\Http\Controllers\Controller;
use App\Models\SolicitudFactura;
use App\Models\User;
use App\Services\Facturas\ListarSolicitudesFacturaService;
use App\Services\Mobile\Serializar\OperacionMovilSerializer;
use App\Support\Api\V1\AutorizacionMovil;
use App\Support\Api\V1\RespuestaPaginada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacturaMobileController extends Controller
{
    public function index(Request $request, ListarSolicitudesFacturaService $listar, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['facturas.ver_listado']);
        $usuario->loadMissing('departamentos');

        $filtros = $request->only(['q', 'tab', 'departamento_id', 'area_id', 'vendedor_id', 'fecha_inicio', 'fecha_fin']);
        $paginado = $listar->ejecutar($usuario, $filtros, true, RespuestaPaginada::porPagina($request));

        return response()->json([
            'data' => $paginado->getCollection()->map(fn (SolicitudFactura $factura) => $serializar->solicitudFactura($factura))->values(),
            'meta' => RespuestaPaginada::meta($paginado),
            'metricas' => $listar->metricas($usuario, $filtros),
        ]);
    }

    public function show(Request $request, SolicitudFactura $solicitudFactura, ListarSolicitudesFacturaService $listar, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['facturas.ver_listado']);
        $usuario->loadMissing('departamentos');
        abort_unless($listar->usuarioPuedeVer($usuario, $solicitudFactura), 404, 'Solicitud de facturación no encontrada.');

        $solicitudFactura->load([
            'vendedor:id,name',
            'departamento:id,nombre',
            'estado:id,nombre',
            'cliente:id,numero_cliente,nombre',
            'banco:id,nombre',
            'respondidaPor:id,name',
            'receptorFiscal:id,codigo_interno',
        ]);

        return response()->json([
            'data' => $serializar->solicitudFactura($solicitudFactura),
        ]);
    }

    private function usuario(Request $request): User
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $usuario;
    }
}
