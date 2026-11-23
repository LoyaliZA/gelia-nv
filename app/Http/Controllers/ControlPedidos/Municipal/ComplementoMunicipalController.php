<?php

namespace App\Http\Controllers\ControlPedidos\Municipal;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use App\Services\ControlPedidos\Municipal\EnviarComplementoMunicipalAAuditoriaService;
use App\Services\ControlPedidos\Municipal\IntegrarComplementoMunicipalService;
use App\Services\ControlPedidos\Municipal\RegistrarComplementoMunicipalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** M-03b: piezas adicionales de un envío municipal con remisión. */
class ComplementoMunicipalController extends Controller
{
    public function store(Request $request, PedidoBma $pedidoBma, ListarPedidosBmaService $listar, RegistrarComplementoMunicipalService $servicio): JsonResponse
    {
        Gate::authorize('control_pedidos.crear');
        $listar->asegurarAcceso($pedidoBma, Auth::user());
        $datos = $request->validate([
            'solicitud_id' => ['required', 'uuid'],
            'folio_remision' => ['required', 'string', 'max:60'],
            'total_mercancia' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'piezas' => ['required', 'array', 'min:1', 'max:20'],
            'piezas.*.descripcion' => ['required', 'string', 'max:180'],
            'piezas.*.cantidad' => ['required', 'integer', 'min:1'],
            'evidencias' => ['required', 'array', 'min:1', 'max:8'],
            'evidencias.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);
        $complemento = $servicio->ejecutar($pedidoBma, Auth::user(), $datos);

        return response()->json(['ok' => true, 'complemento' => ['id' => $complemento->id, 'folio' => $complemento->folio]]);
    }

    public function enviar(PedidoBma $pedidoBma, PedidoBma $complemento, ListarPedidosBmaService $listar, EnviarComplementoMunicipalAAuditoriaService $servicio): JsonResponse
    {
        Gate::authorize('control_pedidos.crear');
        $listar->asegurarAcceso($pedidoBma, Auth::user());
        abort_unless((int) $complemento->pedido_principal_id === (int) $pedidoBma->id, 404);
        $resultado = $servicio->ejecutar($complemento, Auth::user());

        return response()->json(['ok' => true, 'complemento' => ['id' => $resultado->id, 'folio' => $resultado->folio]]);
    }

    /** La confirma el área que tiene el paquete (CEDIS o la sucursal); el servicio comprueba permiso y sucursal. */
    public function integrar(PedidoBma $pedidoBma, PedidoBma $complemento, IntegrarComplementoMunicipalService $servicio): JsonResponse
    {
        abort_unless((int) $complemento->pedido_principal_id === (int) $pedidoBma->id, 404);
        $resultado = $servicio->ejecutar($pedidoBma, $complemento, Auth::user());

        return response()->json(['ok' => true, 'complemento' => ['id' => $resultado->id, 'folio' => $resultado->folio]]);
    }
}
