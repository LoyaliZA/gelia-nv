<?php

namespace App\Services\ControlPedidos\Direcciones;

use App\Models\ClienteDireccion;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\Clientes\Direcciones\GestionDireccionesClienteService;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

class ActualizarCamposDireccionPedidoService
{
    public function __construct(
        private GestionDireccionesClienteService $gestionDirecciones,
        private CrearSnapshotDireccionPedido $crearSnapshot,
        private RegistrarHistorialPedidoService $historial,
    ) {}

    /**
     * Actualiza la dirección del catálogo (nueva versión + auditoría) y, si hay pedido,
     * apunta el pedido a la nueva versión y genera snapshot de auditoría.
     *
     * @param  array<string, mixed>  $datos
     * @return array{direccion: ClienteDireccion, pedido: ?PedidoBma}
     */
    public function ejecutar(
        int $clienteId,
        int $direccionId,
        array $datos,
        int $usuarioId,
        ?PedidoBma $pedido = null,
        ?string $motivo = null,
    ): array {
        Gate::authorize('clientes.direcciones.editar');

        $direccion = ClienteDireccion::query()->findOrFail($direccionId);
        if ((int) $direccion->cliente_id !== $clienteId) {
            throw new \InvalidArgumentException('La dirección no pertenece al cliente indicado.');
        }

        return DB::transaction(function () use ($clienteId, $direccion, $datos, $usuarioId, $pedido, $motivo) {
            $nueva = $this->gestionDirecciones->crearNuevaVersion($direccion->id, $datos, [
                'usuario_id' => $usuarioId,
                'origen' => ClienteDireccion::ORIGEN_INTERNAL,
            ]);

            $this->gestionDirecciones->verificar($nueva->id, ['usuario_id' => $usuarioId]);

            $pedidoActualizado = null;
            if ($pedido) {
                if ((int) $pedido->cliente_id !== $clienteId) {
                    throw new \InvalidArgumentException('El pedido no pertenece al cliente de la dirección.');
                }

                $pedido->update(['cliente_direccion_id' => $nueva->id]);

                $motivoSnap = $motivo ?: 'Actualización de campos de dirección desde el pedido.';
                $this->crearSnapshot->ejecutar($pedido->fresh(), $usuarioId, $motivoSnap);

                $this->historial->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $pedido->catalogo_estatus_pedido_id,
                    $pedido->catalogo_estatus_pedido_id,
                    $motivoSnap.' Nueva versión de dirección #'.$nueva->id.'.',
                    AccionesHistorialPedidoBma::CAMBIO_DIRECCION
                );

                $pedidoActualizado = $pedido->fresh(['direccionVigente', 'cliente', 'estatus', 'documentos']);
            }

            return [
                'direccion' => $nueva->fresh(),
                'pedido' => $pedidoActualizado,
            ];
        });
    }
}
