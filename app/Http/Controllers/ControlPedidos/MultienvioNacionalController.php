<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\MultienvioNacional;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\Nacional\Multienvio\ConfirmarMultienvio;
use App\Services\ControlPedidos\Nacional\Multienvio\GuardarBorradorMultienvio;
use App\Services\ControlPedidos\Nacional\Multienvio\MultienvioNacionalException;
use App\Services\ControlPedidos\Nacional\Multienvio\ProyeccionMultienvio;
use App\Services\ControlPedidos\Nacional\Multienvio\RegistrarDecisionMultienvio;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Multienvío nacional: controlador delgado; las reglas viven en App\Services\ControlPedidos\Nacional\Multienvio. */
class MultienvioNacionalController extends Controller
{
    public function __construct(private readonly ProyeccionMultienvio $proyeccion) {}

    public function show(PedidoBma $pedidoBma): JsonResponse
    {
        VisibilidadPedidoBma::assertPuedeConsultar(Auth::user(), $pedidoBma);

        return $this->responder($pedidoBma);
    }

    public function decision(Request $request, PedidoBma $pedidoBma, RegistrarDecisionMultienvio $servicio): JsonResponse
    {
        VisibilidadPedidoBma::assertPuedeMutarComoVendedora(Auth::user(), $pedidoBma);
        $datos = $request->validate([
            'decision' => ['required', 'in:activar,sin_seguro'],
            'revision' => ['required', 'string', 'max:64'],
        ]);

        $this->traducir(fn () => $servicio->ejecutar($pedidoBma, Auth::id(), $datos['decision'], $datos['revision']));

        return $this->responder($pedidoBma);
    }

    public function borrador(Request $request, PedidoBma $pedidoBma, GuardarBorradorMultienvio $servicio): JsonResponse
    {
        VisibilidadPedidoBma::assertPuedeMutarComoVendedora(Auth::user(), $pedidoBma);
        $datos = $request->validate([
            'envios' => ['required', 'array', 'max:50'],
            'envios.*' => ['array'],
            'envios.*.uuid_operativo' => ['required', 'string', 'max:64'],
            'envios.*.folio_remision' => ['nullable', 'string', 'max:80'],
            'envios.*.total_mercancia' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'envios.*.contenido' => ['nullable', 'string', 'max:500'],
        ]);
        $grupo = $this->grupoDelOrigen($pedidoBma);

        $this->traducir(fn () => $servicio->ejecutar($grupo, Auth::id(), $datos['envios']));

        return $this->responder($pedidoBma);
    }

    public function confirmar(Request $request, PedidoBma $pedidoBma, ConfirmarMultienvio $servicio): JsonResponse
    {
        VisibilidadPedidoBma::assertPuedeMutarComoVendedora(Auth::user(), $pedidoBma);
        $datos = $request->validate(['revision' => ['required', 'string', 'max:64']]);
        $grupo = $this->grupoDelOrigen($pedidoBma);

        // Idempotente: un grupo activo o en regularización se devuelve igual (200) y la UI muestra su estado.
        $this->traducir(fn () => $servicio->ejecutar($grupo, Auth::id(), $datos['revision']));

        return $this->responder($pedidoBma);
    }

    private function grupoDelOrigen(PedidoBma $pedido): MultienvioNacional
    {
        return MultienvioNacional::query()->where('pedido_origen_id', $pedido->id)->firstOrFail();
    }

    private function responder(PedidoBma $pedido): JsonResponse
    {
        return response()->json($this->proyeccion->de($pedido->fresh(), Auth::user()));
    }

    /**
     * Errores de dominio -> 422 por campo (formato de validación de Laravel, compatible con Inertia).
     * AuthorizationException se deja subir: el manejador global la responde 403.
     */
    private function traducir(callable $accion): void
    {
        try {
            $accion();
        } catch (MultienvioNacionalException $e) {
            throw ValidationException::withMessages([$e->campo => [$e->getMessage()]]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['envios' => [$e->getMessage()]]);
        }
    }
}
