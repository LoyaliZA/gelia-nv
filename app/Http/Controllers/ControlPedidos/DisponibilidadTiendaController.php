<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\AccionesDisponibilidadTiendaService;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DisponibilidadTiendaController extends Controller
{
    public function show(Request $request, PedidoBma $pedidoBma, ListarPedidosBmaService $listar)
    {
        abort_unless(VisibilidadPedidoBma::puedeConsultar($request->user(), $pedidoBma), 403);

        return DB::transaction(function () use ($request, $pedidoBma, $listar) {
            // Una sola instantánea de pedido y revisión/historial frente a mutaciones concurrentes.
            $pedido = PedidoBma::query()->sharedLock()->findOrFail($pedidoBma->id);
            abort_unless(VisibilidadPedidoBma::puedeConsultar($request->user(), $pedido), 403);

            return response()->json($listar->presentar($pedido, $request->user()));
        });
    }

    public function update(Request $request, PedidoBma $pedidoBma, AccionesDisponibilidadTiendaService $servicio, ListarPedidosBmaService $listar)
    {
        $datos = $request->validate([
            'revision_id' => ['required', 'integer'], 'accion' => ['required', 'in:confirmar,retirar,sustituir'],
            'solicitud_id' => ['required', 'uuid'], 'entrada' => ['nullable', 'in:catalogo,documento'],
            'producto_id' => ['required_if:entrada,catalogo', 'nullable', 'integer'],
            'evidencia' => ['required_if:entrada,documento', 'nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);
        if ($datos['accion'] === 'sustituir' && ! isset($datos['entrada'])) {
            abort(422, 'Seleccione SKU o documento para la sustitución.');
        }
        $pedido = $servicio->ejecutar($pedidoBma, $request->user(), $datos);

        return response()->json($listar->presentar($pedido, $request->user()));
    }

    public function alternativa(Request $request, PedidoBma $pedidoBma, AccionesDisponibilidadTiendaService $servicio, ListarPedidosBmaService $listar)
    {
        $datos = $request->validate(['indice' => ['required', 'integer', 'min:0'], 'decision' => ['required', 'in:disponible,faltante'],
            'producto_id' => ['nullable', 'integer'], 'nota' => ['required', 'string', 'max:500']]);

        return response()->json($listar->presentar($servicio->responderAlternativa($pedidoBma, $request->user(), $datos), $request->user()));
    }
}
