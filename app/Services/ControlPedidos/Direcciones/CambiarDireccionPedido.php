<?php

namespace App\Services\ControlPedidos\Direcciones;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CambiarDireccionPedido
{
    public function __construct(
        private CrearSnapshotDireccionPedido $crearSnapshot,
        private InvalidarGuiaPorCambioDireccion $invalidarGuia,
        private RegistrarHistorialPedidoService $historial,
    ) {}

    /**
     * @param  array{cliente_direccion_id?: int|null, motivo: string, usuario_id: int}  $contexto
     */
    public function ejecutar(PedidoBma $pedido, array $contexto): PedidoBma
    {
        Gate::authorize('control_pedidos.direccion.cambiar');

        $pedido->loadMissing(['estatus', 'documentos']);
        $fase = $pedido->estatus?->fase_ciclo;

        if ($pedido->tieneRemision()) {
            Gate::authorize('control_pedidos.direccion.cambiar_despues_remision');
        }

        if ($pedido->numero_rastreo || $pedido->documentos->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->isNotEmpty()) {
            Gate::authorize('control_pedidos.direccion.cambiar_despues_guia');
        }

        if ($fase === CatalogoEstatusPedido::FASE_ENVIADO || $fase === CatalogoEstatusPedido::FASE_ENTREGADO) {
            throw new \RuntimeException('No se puede cambiar la dirección de un pedido enviado. Cree una incidencia.');
        }

        return DB::transaction(function () use ($pedido, $contexto, $fase) {
            $anterior = $pedido->domicilio_entrega;

            if (! empty($contexto['cliente_direccion_id'])) {
                $pedido->update(['cliente_direccion_id' => $contexto['cliente_direccion_id']]);
            }

            $this->crearSnapshot->ejecutar(
                $pedido->fresh(),
                $contexto['usuario_id'],
                $contexto['motivo']
            );

            $tieneGuia = filled($pedido->numero_rastreo)
                || $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->exists();

            if ($tieneGuia) {
                $this->invalidarGuia->ejecutar($pedido, $contexto['usuario_id'], $contexto['motivo']);
            }

            $this->historial->ejecutar(
                $pedido->id,
                $contexto['usuario_id'],
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                'Cambio de dirección. Anterior: '.($anterior ?: 'N/D').'. Motivo: '.$contexto['motivo'].'. Fase: '.($fase ?: 'N/D')
            );

            return $pedido->fresh(['direccionVigente', 'cliente', 'estatus', 'documentos']);
        });
    }
}
