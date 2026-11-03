<?php

namespace App\Http\Controllers\Api\V1\Mobile\Cancelaciones;

use App\Events\SolicitudOperativaActualizada;
use App\Http\Controllers\Controller;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\CancelacionesCotizaciones\ListarSolicitudesOperativasService;
use App\Services\Mobile\Serializar\OperacionMovilSerializer;
use App\Services\Solicitudes\SolicitarCancelacionSolicitudService;
use App\Support\Api\V1\AutorizacionMovil;
use App\Support\Api\V1\RespuestaPaginada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CancelacionCotizacionMobileController extends Controller
{
    public function index(Request $request, ListarSolicitudesOperativasService $listar, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['cancelaciones_cotizaciones.ver_listado']);
        $usuario->loadMissing('departamentos');

        $filtros = $request->only([
            'q', 'tab', 'tipo_operativo', 'proceso_id', 'departamento_id', 'area_id', 'vendedor_id', 'fecha_inicio', 'fecha_fin',
        ]);
        $paginado = $listar->ejecutar($usuario, $filtros, true, RespuestaPaginada::porPagina($request));

        return response()->json([
            'data' => $paginado->getCollection()->map(fn (SolicitudTag $solicitud) => $serializar->solicitud($solicitud))->values(),
            'meta' => RespuestaPaginada::meta($paginado),
            'metricas' => $listar->metricas($usuario, $filtros),
        ]);
    }

    public function show(Request $request, SolicitudTag $solicitud, ListarSolicitudesOperativasService $listar, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['cancelaciones_cotizaciones.ver_listado']);
        $usuario->loadMissing('departamentos');
        $listar->asegurarOperativa($solicitud);
        abort_unless($listar->usuarioPuedeVer($usuario, $solicitud), 404, 'Solicitud no encontrada.');

        $this->cargar($solicitud);

        return response()->json([
            'data' => $serializar->solicitud($solicitud),
        ]);
    }

    public function solicitarCancelacion(
        Request $request,
        SolicitudTag $solicitud,
        ListarSolicitudesOperativasService $listar,
        SolicitarCancelacionSolicitudService $cancelar,
        OperacionMovilSerializer $serializar,
    ): JsonResponse {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, ['cancelaciones_cotizaciones.solicitar_cancelacion']);
        $usuario->loadMissing('departamentos');
        $listar->asegurarOperativa($solicitud);
        abort_unless($listar->usuarioPuedeVer($usuario, $solicitud), 404, 'Solicitud no encontrada.');

        $datos = $request->validate([
            'motivo_cancelacion' => 'required|string|min:10|max:1000',
        ]);

        $cancelar->ejecutar(
            $solicitud,
            $datos['motivo_cancelacion'],
            null,
            'cancelaciones_cotizaciones.solicitar_cancelacion',
            ['cancelaciones_cotizaciones.verificar', 'cancelaciones_cotizaciones.reportar', 'cancelaciones_cotizaciones.cancelar'],
        );

        event(new SolicitudOperativaActualizada(
            solicitudId: $solicitud->id,
            accion: 'cancelacion_solicitada',
            porUsuarioId: $usuario->id,
            vendedorId: $solicitud->vendedor_id,
            departamentoId: $solicitud->departamento_id,
        ));

        $this->cargar($solicitud->refresh());

        return response()->json([
            'data' => $serializar->solicitud($solicitud),
        ]);
    }

    private function cargar(SolicitudTag $solicitud): void
    {
        $solicitud->load([
            'cliente', 'vendedor', 'proceso', 'estado', 'departamento',
            'tipoCliente', 'listaDescuento', 'listaRebaja', 'banco',
        ]);
    }

    private function usuario(Request $request): User
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $usuario;
    }
}
