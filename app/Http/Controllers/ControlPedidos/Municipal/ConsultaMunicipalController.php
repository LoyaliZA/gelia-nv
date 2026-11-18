<?php

namespace App\Http\Controllers\ControlPedidos\Municipal;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\Municipal\EtiquetaConsultaMunicipal;
use App\Services\ControlPedidos\ResponderDisponibilidadInicialService;
use App\Support\ControlPedidos\ConsultaMunicipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class ConsultaMunicipalController extends Controller
{
    public function tienda(Request $request): Response
    {
        abort_unless($request->user()?->can('control_pedidos.municipal.responder_consulta'), 403);
        $pedidos = PedidoBma::query()
            ->where('solicitud_preparacion_json->modalidad_entrega', 'municipal')
            ->where('solicitud_preparacion_json->consulta_municipal->area', 'tienda')
            ->whereIn('solicitud_preparacion_json->consulta_municipal->estado', [ConsultaMunicipal::PENDIENTE, ConsultaMunicipal::EXCEPCIONES])
            ->tap(fn ($query) => \App\Support\ControlPedidos\AlcanceConsultaMunicipal::aplicarTienda($query, $request->user()))
            ->with(['cliente:id,nombre,numero_cliente', 'paqueteria:id,nombre'])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (PedidoBma $pedido) => [
                'id' => $pedido->id,
                'folio' => $pedido->folio,
                'cliente' => $pedido->cliente?->nombre,
                'terminal_destino' => $pedido->paqueteria?->nombre,
                'etiqueta' => EtiquetaConsultaMunicipal::de($pedido),
                'almacen_id' => ConsultaMunicipal::seguimiento($pedido)['almacen_id'] ?? null,
            ]);

        return Inertia::render('ControlPedidos/Municipal/ConsultasTienda', [
            'pedidos' => $pedidos,
        ]);
    }

    public function responder(\App\Http\Requests\ControlPedidos\ResponderDisponibilidadInicialRequest $request, PedidoBma $pedidoBma, ResponderDisponibilidadInicialService $servicio): JsonResponse
    {
        $usuario = $request->user();
        $area = (string) (ConsultaMunicipal::seguimiento($pedidoBma)['area'] ?? '');
        if ($area === 'cedis') {
            abort_unless($usuario->can('control_pedidos.cedis'), 403);
        } elseif ($area === 'tienda') {
            abort_unless($usuario->can('control_pedidos.municipal.responder_consulta'), 403);
        } else {
            abort(404);
        }
        $datos = $request->validated();
        $archivos = $request->file('evidencias', []);
        if ($archivos instanceof UploadedFile) {
            $archivos = [$archivos];
        }
        $resultado = $servicio->ejecutar($pedidoBma, $usuario, $datos, is_array($archivos) ? $archivos : []);

        return response()->json($resultado);
    }
}
