<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Deja pendiente el segundo traslado. No mueve mercancía ni confirma recepción. */
final class SolicitarSegundoTrasladoTiendaService
{
    public const PERMISO = 'control_pedidos.tienda.autorizar_segundo_traslado';

    public function __construct(
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function ejecutar(PedidoBma $pedido, User $usuario, int $sucursalDestinoId): PedidoBma
    {
        if (! $usuario->can('control_pedidos.crear') && ! $usuario->can('control_pedidos.editar')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para solicitar el cambio de sucursal.',
            ]);
        }

        return DB::transaction(function () use ($pedido, $usuario, $sucursalDestinoId) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            if (! InstruccionEntrega::mercanciaYaSalio($pedido)) {
                throw ValidationException::withMessages([
                    'sucursal' => 'La mercancía aún no sale. El destino se actualiza en la instrucción vigente.',
                ]);
            }

            $destino = Sucursal::query()->whereKey($sucursalDestinoId)->where('activo', true)->first();
            if (! $destino) {
                throw ValidationException::withMessages(['sucursal' => 'Elija una sucursal activa.']);
            }

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $vigente = is_array($json['segundo_traslado'] ?? null) ? $json['segundo_traslado'] : null;
            if (($vigente['estado'] ?? null) === 'pendiente') {
                if ((int) ($vigente['sucursal_destino_id'] ?? 0) === (int) $destino->id) {
                    return $pedido;
                }
                throw ValidationException::withMessages([
                    'sucursal' => 'Ya hay una solicitud de segundo traslado pendiente de gerencia.',
                ]);
            }

            $json['segundo_traslado'] = [
                'estado' => 'pendiente',
                'sucursal_origen_id' => $pedido->sucursal_destino_id,
                'sucursal_destino_id' => $destino->id,
                'solicitada_por_id' => $usuario->id,
                'solicitada_at' => now()->toIso8601String(),
                'resolucion' => null,
                'recorrido' => null,
            ];
            $pedido->update(['solicitud_preparacion_json' => $json]);

            $estatusId = $pedido->estatus?->id ?? $pedido->catalogo_estatus_pedido_id;
            if ($estatusId) {
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $estatusId,
                    $estatusId,
                    "Solicitud de segundo traslado hacia {$destino->nombre}. La mercancía sigue donde está.",
                    AccionesHistorialPedidoBma::CAMBIO_SUCURSAL_DESTINO
                );
            }

            return $pedido->fresh(['sucursalDestino', 'estatus']);
        });
    }
}
