<?php

namespace App\Http\Controllers\ControlPedidos\Municipal;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\Municipal\ActivarResguardoEnvioService;
use App\Services\ControlPedidos\Municipal\CerrarAcumulacionResguardoService;
use App\Services\ControlPedidos\Municipal\EnviarConsultaMunicipalService;
use App\Services\ControlPedidos\Municipal\EtiquetaConsultaMunicipal;
use App\Models\ControlPedidos\PedidoBmaEnvioResguardo;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Services\ControlPedidos\Municipal\IntegrarCustodiaLocalMunicipalService;
use App\Services\ControlPedidos\Municipal\IntegrarRemisionEnCedisMunicipalService;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
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
            'sucursal_destino_id' => ['nullable', 'integer'],
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
            'revision_esperada' => ['required', 'integer'],
            'base_captura' => ['required', 'string'],
        ]);
        $pedido = $servicio->ejecutar($pedidoBma, $request->user(), $datos);

        return response()->json($this->presentar($pedido, $politica));
    }

    public function remisiones(Request $request, PedidoBma $pedidoBma, VincularRemisionPagadaService $servicio): JsonResponse
    {
        VisibilidadPedidoBma::assertPuedeConsultar($request->user(), $pedidoBma);
        abort_unless(ConsultaMunicipal::es($pedidoBma), 404);
        $q = trim((string) $request->query('q', ''));

        return response()->json([
            'data' => $servicio->listar($pedidoBma, $q)->map(fn (PedidoBma $item) => [
                'id' => $item->id,
                'folio' => $item->folio,
                'numero_remision' => $item->numero_remision,
                'folio_remision' => $item->folio_remision,
                'total_mercancia' => $item->total_mercancia,
                'estatus_sucursal' => $item->estatus_sucursal,
                'mecanismos' => $this->mecanismosDisponibles($item),
            ])->values(),
            'vinculos' => PoliticaResguardoEnvio::vinculos($pedidoBma),
        ]);
    }

    public function vincular(Request $request, PedidoBma $pedidoBma, VincularRemisionPagadaService $servicio, PoliticaFinancieraMunicipal $politica): JsonResponse
    {
        VisibilidadPedidoBma::assertPuedeConsultar($request->user(), $pedidoBma);
        $datos = $request->validate([
            'origen_id' => ['required', 'integer'],
            'mecanismo' => ['required', 'in:custodia_cedis,custodia_local,resguardo_envio'],
        ]);
        $origen = PedidoBma::query()->findOrFail((int) $datos['origen_id']);
        abort_unless((int) $origen->cliente_id === (int) $pedidoBma->cliente_id, 404);
        try {
            $pedido = $servicio->ejecutar($pedidoBma, $origen, $request->user(), $datos['mecanismo']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->presentar($pedido, $politica));
    }

    public function desvincular(Request $request, PedidoBma $pedidoBma, PedidoBma $origen, VincularRemisionPagadaService $servicio, PoliticaFinancieraMunicipal $politica): JsonResponse
    {
        VisibilidadPedidoBma::assertPuedeConsultar($request->user(), $pedidoBma);
        $this->assertOrigenDelEnvio($pedidoBma, $origen);
        try {
            $pedido = $servicio->desvincular($pedidoBma, $origen, $request->user());
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->presentar($pedido, $politica));
    }

    /** Confirmación física de una custodia de sucursal integrada localmente. */
    public function integrarLocal(Request $request, PedidoBma $pedidoBma, PedidoBma $origen, IntegrarCustodiaLocalMunicipalService $servicio, PoliticaFinancieraMunicipal $politica): JsonResponse
    {
        $this->assertOrigenDelEnvio($pedidoBma, $origen);
        try {
            $servicio->ejecutar($origen, $request->user(), $pedidoBma);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->presentar($pedidoBma->fresh(), $politica));
    }

    /** Confirmación física, dentro de CEDIS, de una remisión anexada al envío. */
    public function integrarCedis(Request $request, PedidoBma $pedidoBma, PedidoBma $origen, IntegrarRemisionEnCedisMunicipalService $servicio, PoliticaFinancieraMunicipal $politica): JsonResponse
    {
        $this->assertOrigenDelEnvio($pedidoBma, $origen);
        try {
            $pedido = $servicio->ejecutar($pedidoBma, $origen, $request->user());
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->presentar($pedido, $politica));
    }

    /** Una URL con otro envío principal o una remisión ajena responde 404, sin revelar datos. */
    private function assertOrigenDelEnvio(PedidoBma $envio, PedidoBma $origen): void
    {
        abort_unless(ConsultaMunicipal::es($envio) && (int) $origen->cliente_id === (int) $envio->cliente_id, 404);
        $vinculada = (int) $origen->pedido_principal_id === (int) $envio->id
            || PedidoBmaEnvioResguardo::query()
                ->where('pedido_envio_id', $envio->id)
                ->where('pedido_resguardo_id', $origen->id)
                ->exists();
        abort_unless($vinculada, 404);
    }

    /** @return list<string> Mecanismos físicamente posibles según dónde está la remisión. */
    private function mecanismosDisponibles(PedidoBma $origen): array
    {
        $enCustodia = ResguardoPdv::query()
            ->where('pedido_bma_id', $origen->id)
            ->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
            ->exists();

        $json = is_array($origen->solicitud_preparacion_json) ? $origen->solicitud_preparacion_json : [];
        $traslado = in_array($json['retiro_recoleccion']['estado'] ?? null, ['pendiente_traslado', 'en_traslado'], true);
        if ($traslado) {
            // En traslado no es «resguardada en CEDIS»: se espera la recepción antes de vincularla.
            return [];
        }

        return $enCustodia
            ? [PoliticaResguardoEnvio::MECANISMO_LOCAL, PoliticaResguardoEnvio::MECANISMO_CEDIS]
            : [PoliticaResguardoEnvio::MECANISMO_RESGUARDO];
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
