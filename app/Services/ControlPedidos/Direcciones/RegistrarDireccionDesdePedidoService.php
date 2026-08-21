<?php

namespace App\Services\ControlPedidos\Direcciones;

use App\Models\ClienteDireccion;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\Clientes\Direcciones\GestionDireccionesClienteService;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RegistrarDireccionDesdePedidoService
{
    public function __construct(
        private GestionDireccionesClienteService $gestionDirecciones,
        private CrearSnapshotDireccionPedido $crearSnapshot,
        private RegistrarHistorialPedidoService $historial,
    ) {}

    /**
     * Alta en catálogo (verificada) desde la captura del pedido y opcionalmente la enlaza al pedido.
     *
     * @param  array<string, mixed>  $datos
     * @return array{direccion: ClienteDireccion, pedido: ?PedidoBma}
     */
    public function ejecutar(
        int $clienteId,
        array $datos,
        bool $esPrincipal,
        int $usuarioId,
        ?PedidoBma $pedido = null,
    ): array {
        Gate::authorize('clientes.direcciones.crear');

        return DB::transaction(function () use ($clienteId, $datos, $esPrincipal, $usuarioId, $pedido) {
            $ctx = [
                'usuario_id' => $usuarioId,
                'origen' => ClienteDireccion::ORIGEN_INTERNAL,
                'verificar' => true,
                'es_principal' => $esPrincipal,
            ];

            $tieneActivas = ClienteDireccion::query()
                ->where('cliente_id', $clienteId)
                ->activas()
                ->exists();

            if (! $tieneActivas) {
                $direccion = $this->gestionDirecciones->crearPrimeraDireccion(
                    $clienteId,
                    $datos,
                    array_merge($ctx, ['es_principal' => true])
                );
            } else {
                $direccion = $this->gestionDirecciones->crearDireccionAdicional($clienteId, $datos, $ctx);
                if ($esPrincipal) {
                    $this->gestionDirecciones->marcarComoPrincipal($direccion->id, [
                        'usuario_id' => $usuarioId,
                        'origen' => ClienteDireccion::ORIGEN_INTERNAL,
                    ]);
                    $direccion = $direccion->fresh();
                }
            }

            $pedidoActualizado = null;
            if ($pedido) {
                if ((int) $pedido->cliente_id !== $clienteId) {
                    throw new \InvalidArgumentException('El pedido no pertenece al cliente de la dirección.');
                }

                $pedido->update(['cliente_direccion_id' => $direccion->id]);
                $this->crearSnapshot->ejecutar(
                    $pedido->fresh(),
                    $usuarioId,
                    'Dirección registrada en catálogo desde el pedido.'
                );
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $pedido->catalogo_estatus_pedido_id,
                    $pedido->catalogo_estatus_pedido_id,
                    'Dirección de catálogo #'.$direccion->id.' vinculada al pedido.',
                    AccionesHistorialPedidoBma::CAMBIO_DIRECCION
                );
                $pedidoActualizado = $pedido->fresh(['direccionVigente', 'cliente', 'estatus', 'documentos']);
            }

            return [
                'direccion' => $direccion->fresh(),
                'pedido' => $pedidoActualizado,
            ];
        });
    }
}
