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
        $pedidos = \App\Support\ControlPedidos\AlcanceConsultaMunicipal::pendientesTienda($request->user());

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

    public function empacarTienda(Request $request, PedidoBma $pedidoBma, \App\Services\ControlPedidos\Municipal\MarcarEmpacadoMunicipalTiendaService $servicio): JsonResponse
    {
        abort_unless($request->user()?->can('control_pedidos.municipal.responder_consulta'), 403);
        $servicio->ejecutar($pedidoBma, $request->user());

        return response()->json(['ok' => true]);
    }
}
