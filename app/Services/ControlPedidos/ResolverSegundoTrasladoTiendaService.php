<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Gerencia resuelve. Autorizar cambia la instrucción de destino y no confirma la recepción. */
final class ResolverSegundoTrasladoTiendaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function ejecutar(PedidoBma $pedido, User $usuario, string $decision, string $recorrido): PedidoBma
    {
        if (! $usuario->can(SolicitarSegundoTrasladoTiendaService::PERMISO)) {
            throw ValidationException::withMessages([
                'permiso' => 'El segundo traslado lo resuelve gerencia. Autorizar una salida no equivale a esa facultad.',
            ]);
        }

        $decision = trim($decision);
        $recorrido = trim($recorrido);
        if (! in_array($decision, ['autorizar', 'rechazar'], true)) {
            throw ValidationException::withMessages(['decision' => 'Autorice o rechace la solicitud.']);
        }
        if ($decision === 'autorizar' && $recorrido === '') {
            throw ValidationException::withMessages([
                'recorrido' => 'Indique el recorrido confirmado. Autorizar no mueve la mercancía ni confirma la recepción.',
            ]);
        }

        return DB::transaction(function () use ($pedido, $usuario, $decision, $recorrido) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $pedido->loadMissing('estatus');
            abort_unless(VisibilidadPedidoBma::puedeConsultar($usuario, $pedido), 403);
            if ($pedido->cancelado_at || $pedido->estatus?->fase_ciclo === 'ENTREGADO') {
                throw ValidationException::withMessages(['traslado' => 'El pedido ya está cerrado.']);
            }
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $solicitud = is_array($json['segundo_traslado'] ?? null) ? $json['segundo_traslado'] : null;
            if (($solicitud['estado'] ?? null) !== 'pendiente') {
                throw ValidationException::withMessages([
                    'solicitud' => 'No hay una solicitud de segundo traslado pendiente.',
                ]);
            }

            $estatusSucursal = $pedido->estatus_sucursal;
            $solicitud['estado'] = $decision === 'autorizar' ? 'autorizada' : 'rechazada';
            $solicitud['resuelta_por_id'] = $usuario->id;
            $solicitud['resuelta_at'] = now()->toIso8601String();
            $solicitud['recorrido'] = $recorrido !== '' ? $recorrido : null;
            $solicitud['recepcion_confirmada'] = false;
            $json['segundo_traslado'] = $solicitud;

            $cambios = ['solicitud_preparacion_json' => $json];
            $texto = 'Gerencia rechazó el segundo traslado. La mercancía permanece en su ubicación.';
            if ($decision === 'autorizar') {
                if (! app(MovimientosTiendaService::class)->enCustodia($pedido)) {
                    throw ValidationException::withMessages(['custodia' => 'El pedido debe seguir en custodia en origen para autorizar.']);
                }
                app(ValidarSucursalDestinoPedidoBma::class)->ejecutar($pedido, (int) $solicitud['sucursal_destino_id']);
                $destino = Sucursal::query()->find($solicitud['sucursal_destino_id']);
                $cambios['sucursal_destino_id'] = (int) $solicitud['sucursal_destino_id'];
                $nombre = $destino?->nombre ?: 'la sucursal indicada';
                $texto = "Gerencia autorizó el destino {$nombre}. Recorrido: {$recorrido}. Falta la salida y la recepción; el cliente no recoge en CEDIS.";
            }
            $pedido->update($cambios);
            if ($pedido->fresh()->estatus_sucursal !== $estatusSucursal) {
                $pedido->update(['estatus_sucursal' => $estatusSucursal]);
            }

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

            app(NotificarPedidoBmaService::class)->ejecutar($pedido, 'pedido_traslado_resuelto', $texto, ['control_pedidos.tienda.trasladar'], $usuario->id, true);

            return $pedido->fresh(['sucursalDestino', 'estatus']);
        });
    }
}
