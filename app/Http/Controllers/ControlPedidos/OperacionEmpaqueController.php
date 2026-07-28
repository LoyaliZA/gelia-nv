<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPedidos\ConsolidarPedidosEmpaqueRequest;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\OperacionEmpaque;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\ConsolidarPedidosEmpaqueService;
use App\Services\ControlPedidos\DesconsolidarOperacionEmpaqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class OperacionEmpaqueController extends Controller
{
    private function autorizarAcceso(): void
    {
        if (!Gate::any(['control_pedidos.auditar', 'control_pedidos.cedis'])) {
            abort(403);
        }
    }

    public function candidatos(Request $request): JsonResponse
    {
        $this->autorizarAcceso();

        $clienteId = (int) $request->query('cliente_id');
        if ($clienteId < 1) {
            return response()->json(['data' => []]);
        }

        $excluir = array_filter(array_map('intval', (array) $request->query('excluir', [])));

        $idsPorFase = CatalogoEstatusPedido::query()
            ->whereIn('fase_ciclo', [
                CatalogoEstatusPedido::FASE_EN_CEDIS,
                CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
            ])
            ->pluck('id')
            ->all();

        $pedidos = PedidoBma::query()
            ->with(['estatus:id,fase_ciclo,nombre_visual', 'cliente:id,nombre,numero_cliente'])
            ->where('cliente_id', $clienteId)
            ->whereIn('catalogo_estatus_pedido_id', $idsPorFase ?: [0])
            ->whereNotNull('pago_validado_at')
            ->where('es_resguardo', false)
            ->whereHas('remision')
            ->whereDoesntHave('miembroOperacionEmpaque')
            ->when($excluir, fn ($q) => $q->whereNotIn('id', $excluir))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'folio', 'folio_remision', 'cliente_id', 'total_mercancia', 'numero_cajas', 'peso_real_kg', 'catalogo_estatus_pedido_id']);

        return response()->json(['data' => $pedidos]);
    }

    public function store(
        ConsolidarPedidosEmpaqueRequest $request,
        ConsolidarPedidosEmpaqueService $service
    ): RedirectResponse {
        try {
            $data = $request->validated();
            $operacion = $service->ejecutar(
                $data['pedido_ids'],
                (int) Auth::id(),
                isset($data['principal_id']) ? (int) $data['principal_id'] : null,
                $data['piezas'] ?? []
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with(
            'success',
            "Pedidos consolidados en operación {$operacion->folio_operacion}."
        );
    }

    public function destroy(
        OperacionEmpaque $operacionEmpaque,
        DesconsolidarOperacionEmpaqueService $service
    ): RedirectResponse {
        $this->autorizarAcceso();

        try {
            $service->ejecutar($operacionEmpaque, (int) Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Operación de empaque desconsolidada.');
    }
}
