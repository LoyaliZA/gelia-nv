<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use App\Services\ControlPedidos\RegistrarCreditoPagadoTiendaService;
use App\Support\ControlPedidos\LecturaFinancieraPedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CreditoPagadoTiendaController extends Controller
{
    public function store(
        Request $request,
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listar,
        RegistrarCreditoPagadoTiendaService $servicio,
    ): JsonResponse {
        Gate::authorize('control_pedidos.crear');
        $listar->asegurarAcceso($pedidoBma, Auth::user());
        $request->validate([
            'evidencia' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        try {
            $pedido = $servicio->ejecutar($pedidoBma, Auth::user(), $request->file('evidencia'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(array_merge(LecturaFinancieraPedido::de($pedido), [
            'motivo_rechazo' => $pedido->motivo_rechazo,
        ]));
    }
}
