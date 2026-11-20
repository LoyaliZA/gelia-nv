<?php

namespace App\Http\Controllers\ControlPedidos\Municipal;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\Municipal\ActivarResguardoEnvioService;
use App\Services\ControlPedidos\Municipal\CerrarAcumulacionResguardoService;
use App\Services\ControlPedidos\Municipal\EnviarConsultaMunicipalService;
use App\Services\ControlPedidos\Municipal\EtiquetaConsultaMunicipal;
use App\Services\ControlPedidos\Municipal\IntegrarCustodiaLocalMunicipalService;
use App\Services\ControlPedidos\Municipal\PoliticaFinancieraMunicipal;
use App\Services\ControlPedidos\Municipal\RegistrarSolicitudMunicipalService;
use App\Services\ControlPedidos\Municipal\SolicitarContinuacionResguardoService;
use App\Services\ControlPedidos\Municipal\VincularRemisionPagadaService;
use App\Support\ControlPedidos\ConsultaMunicipal;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
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

    public function resguardo(Request $request, PedidoBma $pedidoBma, ActivarResguardoEnvioService $servicio, PoliticaFinancieraMunicipal $politica): JsonResponse
    {
        $datos = $request->validate([
            'activo' => ['required', 'boolean'],
            'motivo' => ['nullable', 'in:fecha_posterior,acumulacion,espera_complemento'],
            'fecha_solicitada' => ['nullable', 'date'],
            'revision_esperada' => ['required', 'integer'],
            'base_captura' => ['required', 'string'],
        ]);
        $pedido = $servicio->ejecutar($pedidoBma, $request->user(), $datos);

        return response()->json($this->presentar($pedido, $politica));
    }

    public function continuacion(Request $request, PedidoBma $pedidoBma, SolicitarContinuacionResguardoService $servicio, PoliticaFinancieraMunicipal $politica): JsonResponse
    {
        $datos = $request->validate([
            'revision_esperada' => ['required', 'integer'],
            'base_captura' => ['required', 'string'],
        ]);
        $pedido = $servicio->ejecutar($pedidoBma, $request->user(), $datos);

        return response()->json($this->presentar($pedido, $politica));
    }

    public function cerrarAcumulacion(Request $request, PedidoBma $pedidoBma, CerrarAcumulacionResguardoService $servicio, PoliticaFinancieraMunicipal $politica): JsonResponse
    {
        $datos = $request->validate([
            'revision_esperada' => ['nullable', 'integer'],
            'base_captura' => ['nullable', 'string'],
        ]);
        $pedido = $servicio->ejecutar($pedidoBma, $request->user(), $datos);

        return response()->json($this->presentar($pedido, $politica));
    }

    public function remisiones(Request $request, PedidoBma $pedidoBma, VincularRemisionPagadaService $servicio): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json([
            'data' => $servicio->listar($pedidoBma, $q)->map(fn (PedidoBma $item) => [
                'id' => $item->id,
                'folio' => $item->folio,
                'numero_remision' => $item->numero_remision,
                'folio_remision' => $item->folio_remision,
                'total_mercancia' => $item->total_mercancia,
                'estatus_sucursal' => $item->estatus_sucursal,
            ])->values(),
            'vinculos' => PoliticaResguardoEnvio::vinculos($pedidoBma),
        ]);
    }

    public function vincular(Request $request, PedidoBma $pedidoBma, VincularRemisionPagadaService $servicio, PoliticaFinancieraMunicipal $politica): JsonResponse
    {
        $datos = $request->validate([
            'origen_id' => ['required', 'integer'],
            'mecanismo' => ['required', 'in:custodia_cedis,custodia_local,resguardo_envio'],
        ]);
        $origen = PedidoBma::query()->findOrFail((int) $datos['origen_id']);
        try {
            $pedido = $servicio->ejecutar($pedidoBma, $origen, $request->user(), $datos['mecanismo']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->presentar($pedido, $politica));
    }

    public function desvincular(PedidoBma $pedidoBma, PedidoBma $origen, VincularRemisionPagadaService $servicio, PoliticaFinancieraMunicipal $politica): JsonResponse
    {
        try {
            $pedido = $servicio->desvincular($pedidoBma, $origen, request()->user());
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->presentar($pedido, $politica));
    }

    public function integrarLocal(PedidoBma $pedidoBma, PedidoBma $origen, IntegrarCustodiaLocalMunicipalService $servicio, PoliticaFinancieraMunicipal $politica): JsonResponse
    {
        try {
            $servicio->ejecutar($origen, request()->user());
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->presentar($pedidoBma->fresh(), $politica));
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
            'es_resguardo' => (bool) $pedido->es_resguardo,
            'politica' => ConsultaMunicipal::es($pedido) ? $politica->evaluar($pedido) : null,
            'resguardo_envio' => ConsultaMunicipal::es($pedido) ? PoliticaResguardoEnvio::evaluar($pedido) : null,
            'revision_registro' => (int) $pedido->revision_registro,
            'base_captura' => $pedido->base_captura,
        ];
    }
}
