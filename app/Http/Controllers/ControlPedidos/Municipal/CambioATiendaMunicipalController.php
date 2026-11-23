<?php

namespace App\Http\Controllers\ControlPedidos\Municipal;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use App\Services\ControlPedidos\Municipal\AplicarCambioMunicipalATiendaService;
use App\Services\ControlPedidos\Municipal\SolicitarCambioMunicipalATiendaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** Cambio de un envío municipal a recolección en sucursal. */
class CambioATiendaMunicipalController extends Controller
{
    public function solicitar(Request $request, PedidoBma $pedidoBma, ListarPedidosBmaService $listar, SolicitarCambioMunicipalATiendaService $servicio): JsonResponse
    {
        Gate::authorize('control_pedidos.crear');
        $listar->asegurarAcceso($pedidoBma, Auth::user());
        $datos = $request->validate([
            'solicitud_id' => ['required', 'uuid'],
            'motivo' => ['required', 'string', 'max:'.SolicitarCambioMunicipalATiendaService::MAX_MOTIVO],
            'error_captura' => ['nullable', 'boolean'],
            'sucursal_destino_id' => ['nullable', 'integer', 'exists:sucursales,id'],
            'evidencias' => ['nullable', 'array', 'max:8'],
            'evidencias.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);
        $pedido = $servicio->ejecutar($pedidoBma, Auth::user(), $datos);

        return response()->json(['ok' => true, 'estado' => $pedido->solicitud_preparacion_json['cambio_a_tienda']['estado'] ?? null]);
    }

    /** Lo verifica el área que tiene el paquete; el servicio comprueba permiso y sucursal. */
    public function aplicar(PedidoBma $pedidoBma, AplicarCambioMunicipalATiendaService $servicio): JsonResponse
    {
        $pedido = $servicio->ejecutar($pedidoBma, Auth::user());

        return response()->json(['ok' => true, 'estado' => $pedido->solicitud_preparacion_json['cambio_a_tienda']['estado'] ?? null]);
    }
}
