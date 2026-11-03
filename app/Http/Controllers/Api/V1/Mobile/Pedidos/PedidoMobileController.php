<?php

namespace App\Http\Controllers\Api\V1\Mobile\Pedidos;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use App\Services\Mobile\Serializar\PedidoMovilSerializer;
use App\Support\Api\V1\AutorizacionMovil;
use App\Support\Api\V1\RespuestaPaginada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PedidoMobileController extends Controller
{
    public function index(
        Request $request,
        ListarPedidosBmaService $listar,
        PedidoMovilSerializer $serializar,
    ): JsonResponse {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['control_pedidos.ver_listado']);

        $paginado = $listar->ejecutar(
            $usuario,
            $request->only(['q', 'tab']),
            true,
            RespuestaPaginada::porPagina($request),
        );

        return response()->json([
            'data' => $paginado->getCollection()->map(fn (PedidoBma $pedido) => $serializar->resumen($pedido))->values(),
            'meta' => RespuestaPaginada::meta($paginado),
            'metricas' => $listar->metricas($usuario),
            'filtros' => [
                'q' => $request->query('q'),
                'tab' => strtoupper((string) $request->query('tab', 'TODAS')),
            ],
        ]);
    }

    public function show(
        Request $request,
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listar,
        PedidoMovilSerializer $serializar,
    ): JsonResponse {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['control_pedidos.ver_listado']);
        $listar->asegurarConsulta($pedidoBma, $usuario);

        return response()->json([
            'data' => $serializar->detalle($listar->presentar($pedidoBma, $usuario)),
        ]);
    }

    private function usuario(Request $request): User
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $usuario;
    }
}
