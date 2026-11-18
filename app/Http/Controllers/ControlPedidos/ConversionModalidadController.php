<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPedidos\CancelarConversionModalidadRequest;
use App\Http\Requests\ControlPedidos\ConfirmarConversionModalidadRequest;
use App\Http\Requests\ControlPedidos\ReservaConversionModalidadRequest;
use App\Http\Requests\ControlPedidos\VistaPreviaConversionModalidadRequest;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\Conversion\CambiarModalidadPedidoService;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use App\Support\ControlPedidos\Conversion\SolicitudConfirmarConversion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ConversionModalidadController extends Controller
{
    public function __construct(
        private CambiarModalidadPedidoService $servicio,
        private ListarPedidosBmaService $listar,
    ) {}

    public function show(Request $request, PedidoBma $pedidoBma): JsonResponse
    {
        $this->listar->asegurarAcceso($pedidoBma, $request->user());

        return response()->json($this->servicio->flags($pedidoBma, $request->user())->toArray());
    }

    public function vistaPrevia(VistaPreviaConversionModalidadRequest $request, PedidoBma $pedidoBma): JsonResponse
    {
        $this->listar->asegurarAcceso($pedidoBma, $request->user());
        $datos = $request->validated();

        return response()->json(
            $this->servicio->vistaPrevia($pedidoBma, $request->user(), $datos['destino'], $datos)->toArray()
        );
    }

    public function confirmar(ConfirmarConversionModalidadRequest $request, PedidoBma $pedidoBma): JsonResponse
    {
        $this->listar->asegurarAcceso($pedidoBma, $request->user());
        $datos = $request->validated();
        $evidencias = $request->file('evidencias', []);
        $evidencias = is_array($evidencias) ? array_values(array_filter($evidencias, static fn ($archivo) => $archivo instanceof UploadedFile)) : [];
        $solicitud = new SolicitudConfirmarConversion(
            $datos['destino'],
            $datos['instancia_id'],
            $datos['motivo'],
            (bool) ($datos['error_captura'] ?? false),
            $evidencias,
            (int) $datos['catalogo_paqueteria_id'],
            (int) $datos['consignatario_id'],
            isset($datos['almacen_id']) ? (int) $datos['almacen_id'] : null,
            $datos['leyenda_terminal'],
            $datos['costo_transporte'],
            $datos['costo_envio'] ?? null,
            (int) $datos['revision_esperada'],
            (int) $datos['conversion_version'],
            $datos['clave_idempotencia'],
        );

        return response()->json($this->servicio->confirmar($pedidoBma, $request->user(), $solicitud)->toArray());
    }

    public function cancelar(CancelarConversionModalidadRequest $request, PedidoBma $pedidoBma): JsonResponse
    {
        $this->listar->asegurarAcceso($pedidoBma, $request->user());
        $datos = $request->validated();

        return response()->json(
            $this->servicio->cancelar(
                $pedidoBma,
                $request->user(),
                $datos['instancia_id'],
                (int) $datos['revision_esperada']
            )->toArray()
        );
    }

    public function abrirReserva(ReservaConversionModalidadRequest $request, PedidoBma $pedidoBma): JsonResponse
    {
        $this->listar->asegurarAcceso($pedidoBma, $request->user());

        return response()->json($this->servicio->reservar($pedidoBma, $request->user(), $request->validated()['instancia_id']));
    }

    public function latidoReserva(ReservaConversionModalidadRequest $request, PedidoBma $pedidoBma): JsonResponse
    {
        $this->listar->asegurarAcceso($pedidoBma, $request->user());

        return response()->json($this->servicio->latido($pedidoBma, $request->user(), $request->validated()['instancia_id']));
    }

    public function soltarReserva(ReservaConversionModalidadRequest $request, PedidoBma $pedidoBma): JsonResponse
    {
        $this->listar->asegurarAcceso($pedidoBma, $request->user());

        return response()->json($this->servicio->soltarReserva($pedidoBma, $request->user(), $request->validated()['instancia_id']));
    }
}
