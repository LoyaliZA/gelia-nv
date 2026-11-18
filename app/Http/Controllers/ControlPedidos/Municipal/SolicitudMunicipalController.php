<?php

namespace App\Http\Controllers\ControlPedidos\Municipal;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\Municipal\EnviarConsultaMunicipalService;
use App\Services\ControlPedidos\Municipal\EtiquetaConsultaMunicipal;
use App\Services\ControlPedidos\Municipal\PoliticaFinancieraMunicipal;
use App\Services\ControlPedidos\Municipal\RegistrarSolicitudMunicipalService;
use App\Support\ControlPedidos\ConsultaMunicipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SolicitudMunicipalController extends Controller
{
    public function guardar(Request $request, PedidoBma $pedidoBma, RegistrarSolicitudMunicipalService $servicio, PoliticaFinancieraMunicipal $politica): JsonResponse
    {
        $datos = $request->validate([
            'catalogo_paqueteria_id' => ['required', 'integer'],
            'almacen_id' => ['nullable', 'integer'],
            'consignatario_id' => ['required', 'integer'],
            'leyenda_terminal' => ['required', 'in:PAGADO,POR_COBRAR'],
            'costo_transporte' => ['required', 'in:conocido,diferido'],
            'costo_envio' => ['nullable', 'numeric', 'min:0'],
        ]);
        $pedido = $servicio->ejecutar($pedidoBma, $request->user(), $datos);

        return response()->json($this->presentar($pedido, $politica));
    }

    public function enviarConsulta(Request $request, PedidoBma $pedidoBma, EnviarConsultaMunicipalService $servicio, PoliticaFinancieraMunicipal $politica): JsonResponse
    {
        $datos = $request->validate(['almacen_id' => ['nullable', 'integer']]);
        $pedido = $servicio->ejecutar($pedidoBma, $request->user(), isset($datos['almacen_id']) ? (int) $datos['almacen_id'] : null);

        return response()->json($this->presentar($pedido, $politica));
    }

    /** @return array<string, mixed> */
    private function presentar(PedidoBma $pedido, PoliticaFinancieraMunicipal $politica): array
    {
        return [
            'id' => $pedido->id,
            'catalogo_paqueteria_id' => $pedido->catalogo_paqueteria_id,
            'paqueteria' => $pedido->paqueteria?->only(['id', 'nombre']),
            'solicitud_preparacion' => $pedido->solicitud_preparacion_json,
            'etiqueta_consulta' => EtiquetaConsultaMunicipal::de($pedido),
            'estado_consulta' => ConsultaMunicipal::es($pedido) ? ConsultaMunicipal::estado($pedido) : null,
            'estatus_envio' => $pedido->estatus_envio,
            'costo_envio' => $pedido->costo_envio,
            'politica' => ConsultaMunicipal::es($pedido) ? $politica->evaluar($pedido) : null,
        ];
    }
}
