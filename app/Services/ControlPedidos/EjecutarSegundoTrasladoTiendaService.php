<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Autorizar no traslada. La salida y la recepción son hechos distintos. */
final class EjecutarSegundoTrasladoTiendaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificar,
    ) {}

    public function salir(PedidoBma $pedido, User $usuario): PedidoBma
    {
        if (! $usuario->can('control_pedidos.tienda.trasladar') && ! $usuario->can('control_pedidos.tienda.responder')) {
            throw ValidationException::withMessages([
                'permiso' => 'Quien tiene la mercancía registra la salida.',
            ]);
        }

        return DB::transaction(function () use ($pedido, $usuario) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = $this->json($pedido);
            $solicitud = $this->autorizada($json);
            if (($solicitud['movimiento_estado'] ?? null) === 'en_camino') {
                return $pedido;
            }
            if (($solicitud['recepcion_confirmada'] ?? false) === true) {
                throw ValidationException::withMessages(['traslado' => 'La recepción ya está confirmada.']);
            }

            $solicitud['movimiento_estado'] = 'en_camino';
            $solicitud['salida_at'] = now()->toIso8601String();
            $solicitud['salida_por_id'] = $usuario->id;
            $solicitud['recepcion_confirmada'] = false;
            $json['segundo_traslado'] = $solicitud;
            $pedido->update([
                'solicitud_preparacion_json' => $json,
                'estatus_sucursal' => EstatusSucursalPedidoBma::EN_TRANSITO,
            ]);
            ResguardoPdv::query()
                ->where('pedido_bma_id', $pedido->id)
                ->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
                ->update(['estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION]);

            $this->anotar($pedido, $usuario, 'Salió el segundo traslado. Falta la recepción en destino. El cliente no recoge en CEDIS.');
            $this->notificar->ejecutar(
                $pedido->fresh(['cliente', 'vendedor']),
                'pedido_segundo_traslado_salida',
                'La mercancía va hacia la sucursal autorizada. Falta confirmar la recepción.',
                ['control_pedidos.tienda.ver'],
                $usuario->id,
                true
            );

            return $pedido->fresh();
        });
    }

    public function recibir(PedidoBma $pedido, User $usuario): PedidoBma
    {
        if (! $usuario->can('control_pedidos.tienda.responder') && ! $usuario->can('control_pedidos.tienda.trasladar')) {
            throw ValidationException::withMessages([
                'permiso' => 'La sucursal de destino confirma la recepción.',
            ]);
        }

        return DB::transaction(function () use ($pedido, $usuario) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = $this->json($pedido);
            $solicitud = $this->autorizada($json);
            if (($solicitud['movimiento_estado'] ?? null) !== 'en_camino') {
                throw ValidationException::withMessages([
                    'traslado' => 'Primero registre la salida. La autorización no mueve la mercancía.',
                ]);
            }

            $destinoId = (int) $solicitud['sucursal_destino_id'];
            $solicitud['movimiento_estado'] = 'recibido';
            $solicitud['recepcion_confirmada'] = true;
            $solicitud['recepcion_at'] = now()->toIso8601String();
            $solicitud['recepcion_por_id'] = $usuario->id;
            $json['segundo_traslado'] = $solicitud;
            $pedido->update([
                'solicitud_preparacion_json' => $json,
                'estatus_sucursal' => EstatusSucursalPedidoBma::EN_CUSTODIA,
                'sucursal_destino_id' => $destinoId,
            ]);
            ResguardoPdv::query()
                ->where('pedido_bma_id', $pedido->id)
                ->whereIn('estado', [ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, ResguardoPdv::ESTADO_EN_CUSTODIA])
                ->update([
                    'sucursal_id' => $destinoId,
                    'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
                    'recepcion_fisica_at' => now(),
                    'entrega_bloqueada' => false,
                ]);

            $this->anotar($pedido, $usuario, 'La sucursal de destino confirmó la recepción del segundo traslado.');
            $this->notificar->ejecutar(
                $pedido->fresh(['cliente', 'vendedor']),
                'pedido_segundo_traslado_recibido',
                'La mercancía llegó a la sucursal de destino y quedó en custodia.',
                ['control_pedidos.crear'],
                $usuario->id,
                true
            );

            return $pedido->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function autorizada(array $json): array
    {
        $solicitud = is_array($json['segundo_traslado'] ?? null) ? $json['segundo_traslado'] : [];
        if (($solicitud['estado'] ?? null) !== 'autorizada') {
            throw ValidationException::withMessages([
                'traslado' => 'Gerencia todavía no autoriza el segundo traslado.',
            ]);
        }

        return $solicitud;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(PedidoBma $pedido): array
    {
        return is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
    }

    private function anotar(PedidoBma $pedido, User $usuario, string $texto): void
    {
        $estatusId = $pedido->catalogo_estatus_pedido_id;
        if ($estatusId) {
            $this->historial->ejecutar(
                $pedido->id,
                $usuario->id,
                $estatusId,
                $estatusId,
                $texto,
                AccionesHistorialPedidoBma::CAMBIO_SUCURSAL_DESTINO
            );
        }
    }
}
