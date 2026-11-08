<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\CambioCoberturaTiendaService;
use App\Support\ControlPedidos\CambioCoberturaTienda;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CambioCoberturaTiendaController extends Controller
{
    public function show(Request $request, PedidoBma $pedidoBma): JsonResponse
    {
        abort_unless(VisibilidadPedidoBma::puedeConsultar($request->user(), $pedidoBma), 403);

        return response()->json(CambioCoberturaTienda::estado($pedidoBma, $request->user()));
    }

    public function update(Request $request, PedidoBma $pedidoBma, CambioCoberturaTiendaService $service): JsonResponse
    {
        $datos = $request->validate([
            'accion' => ['required', 'in:solicitar,autorizar,rechazar'],
            'solicitud_id' => ['required', 'uuid'],
            'version' => ['required_if:accion,solicitar', 'string', 'size:64'],
            'cobertura' => ['required_if:accion,solicitar', 'in:apartado,transferencia,credito'],
            'mismo_dia' => ['nullable', 'boolean'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        if (mb_strlen(trim($datos['motivo'])) < 5) abort(422, 'Indique un motivo de al menos cinco caracteres.');
        $pedido = $service->ejecutar($pedidoBma, $request->user(), $datos);

        return response()->json([
            'estado_cobertura' => CambioCoberturaTienda::estado($pedido, $request->user()),
            'solicitud_preparacion' => $pedido->solicitud_preparacion,
        ]);
    }
}
