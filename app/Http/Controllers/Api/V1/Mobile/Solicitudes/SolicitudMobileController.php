<?php

namespace App\Http\Controllers\Api\V1\Mobile\Solicitudes;

use App\Http\Controllers\Controller;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\Mobile\Serializar\OperacionMovilSerializer;
use App\Services\Solicitudes\ListarSolicitudesService;
use App\Services\Solicitudes\SolicitarCancelacionSolicitudService;
use App\Services\Solicitudes\ValidarListaInferiorService;
use App\Support\Api\V1\AutorizacionMovil;
use App\Support\Api\V1\RespuestaPaginada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SolicitudMobileController extends Controller
{
    public function index(Request $request, ListarSolicitudesService $listar, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['solicitudes.ver_listado']);

        $filtros = $this->filtros($request);
        $paginado = $listar->ejecutar($usuario, $filtros, true, RespuestaPaginada::porPagina($request));

        return response()->json([
            'data' => $paginado->getCollection()->map(fn (SolicitudTag $solicitud) => $serializar->solicitud($solicitud))->values(),
            'meta' => RespuestaPaginada::meta($paginado),
            'metricas' => $listar->metricas($usuario),
            'resumen' => $listar->resumenFiltrado($usuario, $filtros),
        ]);
    }

    public function show(Request $request, SolicitudTag $solicitud, ListarSolicitudesService $listar, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['solicitudes.ver_listado']);
        abort_unless($listar->visiblePara($usuario, $solicitud), 404, 'Solicitud no encontrada.');

        $this->cargar($solicitud);

        return response()->json([
            'data' => $serializar->solicitud($solicitud),
        ]);
    }

    public function solicitarCancelacion(
        Request $request,
        SolicitudTag $solicitud,
        ListarSolicitudesService $listar,
        SolicitarCancelacionSolicitudService $cancelar,
        ValidarListaInferiorService $listas,
        OperacionMovilSerializer $serializar,
    ): JsonResponse {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['solicitudes.solicitar_cancelacion']);
        abort_unless($listar->visiblePara($usuario, $solicitud), 404, 'Solicitud no encontrada.');

        $solicitud->loadMissing(['proceso', 'cliente.listaDescuento', 'listaDescuento']);
        if ($solicitud->proceso?->esOperativo()) {
            abort(403, 'Las solicitudes operativas se cancelan desde el módulo de cancelaciones y cotizaciones.');
        }

        $exigeRebaja = $listas->exigeListaRebaja($solicitud);
        $datos = $request->validate([
            'motivo_cancelacion' => 'required|string|min:10|max:1000',
            'catalogo_lista_rebaja_id' => ($exigeRebaja ? 'required' : 'nullable').'|integer|exists:catalogo_listas_descuento,id',
        ]);

        $cancelar->ejecutar(
            $solicitud,
            $datos['motivo_cancelacion'],
            isset($datos['catalogo_lista_rebaja_id']) ? (int) $datos['catalogo_lista_rebaja_id'] : null,
        );

        $this->cargar($solicitud->refresh());

        return response()->json([
            'data' => $serializar->solicitud($solicitud),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtros(Request $request): array
    {
        return $request->only([
            'q', 'tab', 'estado_id', 'proceso_id', 'vendedor_id', 'lista_id', 'tipo_cliente_id',
            'fecha_inicio', 'fecha_fin', 'motivo_incorrecta',
        ]);
    }

    private function cargar(SolicitudTag $solicitud): void
    {
        $solicitud->load([
            'cliente', 'vendedor', 'proceso', 'estado', 'departamento',
            'tipoCliente', 'listaDescuento', 'listaRebaja', 'banco', 'remisionesFactura',
        ]);
    }

    private function usuario(Request $request): User
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $usuario;
    }
}
