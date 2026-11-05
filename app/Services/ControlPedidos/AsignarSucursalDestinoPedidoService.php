<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
use Illuminate\Support\Facades\DB;

class AsignarSucursalDestinoPedidoService
{
    public function __construct(
        private ValidarSucursalDestinoPedidoBma $validar,
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(
        PedidoBma $pedido,
        User $usuario,
        ?int $sucursalDestinoId,
        ?string $codigoModalidad = null,
        array $datosTraslado = [],
    ): PedidoBma {
        if (! $usuario->can('control_pedidos.crear') && ! $usuario->can('control_pedidos.editar')) {
            throw new \RuntimeException('No tiene permiso para asignar o cambiar la sucursal destino.');
        }

        $nuevo = $sucursalDestinoId !== null ? (int) $sucursalDestinoId : null;

        $this->validar->ejecutar($pedido, $nuevo, $codigoModalidad, false);

        $viejo = $pedido->sucursal_destino_id !== null ? (int) $pedido->sucursal_destino_id : null;
        if ($viejo === $nuevo) {
            return $pedido;
        }

        return DB::transaction(function () use ($pedido, $usuario, $nuevo, $datosTraslado) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $pedido->loadMissing(['estatus', 'sucursalDestino']);
            abort_unless((int) $pedido->vendedor_id === (int) $usuario->id, 403);
            if ($pedido->cancelado_at || $pedido->estatus?->fase_ciclo === 'ENTREGADO') {
                throw new \RuntimeException('El pedido ya está cerrado.');
            }
            $viejo = $pedido->sucursal_destino_id;
            if ((int) $viejo === (int) $nuevo) {
                return $pedido;
            }
            if (InstruccionEntrega::mercanciaYaSalio($pedido)) {
                if ($nuevo === null) {
                    throw new \RuntimeException(InstruccionEntrega::MENSAJE_SEGUNDO_TRASLADO);
                }

                return app(SolicitarSegundoTrasladoTiendaService::class)->ejecutar($pedido, $usuario, $nuevo, $datosTraslado);
            }

            $etiquetaAnterior = $this->etiquetaSucursal($viejo, $pedido->sucursalDestino);
            $pedido->update(['sucursal_destino_id' => $nuevo]);
            $pedido->load('sucursalDestino');
            $etiquetaNueva = $this->etiquetaSucursal($nuevo, $pedido->sucursalDestino);

            $estatusId = $pedido->estatus?->id;
            if ($estatusId) {
                $this->historialService->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $estatusId,
                    $estatusId,
                    "Sucursal destino: {$etiquetaAnterior} → {$etiquetaNueva}.",
                    AccionesHistorialPedidoBma::CAMBIO_SUCURSAL_DESTINO
                );
            }

            app(NotificarPedidoBmaService::class)->ejecutar($pedido, 'pedido_destino_actualizado', 'Sucursal de recolección actualizada antes de la salida: '.$etiquetaNueva.'. Conserve las bolsas y documentos del pedido.', ['control_pedidos.cedis', 'control_pedidos.tienda.trasladar'], $usuario->id, true);

            return $pedido->fresh(['sucursalDestino', 'estatus']);
        });
    }

    private function etiquetaSucursal(?int $id, ?Sucursal $cargada): string
    {
        if ($id === null) {
            return '(sin destino)';
        }

        if ($cargada && (int) $cargada->id === $id) {
            return $cargada->nombre.' (#'.$id.')';
        }

        $sucursal = Sucursal::query()->find($id);

        return $sucursal
            ? $sucursal->nombre.' (#'.$id.')'
            : '#'.$id;
    }
}
