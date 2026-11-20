<?php

namespace App\Services\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\CancelarPedidoBmaService;
use App\Support\ControlPedidos\Nacional\CapturaNacional;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Separa la solicitud, la mercancía, los pagos y la guía. No decide quién absorbe el gasto. */
class RegistrarHechoCancelacionNacional
{
    public function __construct(
        private CancelarPedidoBmaService $cancelar,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public function ejecutar(PedidoBma $pedido, User $usuario, array $datos): PedidoBma
    {
        if (! CapturaNacional::es($pedido)) {
            throw new RuntimeException('Este pedido no usa la captura nacional.');
        }

        return DB::transaction(function () use ($pedido, $usuario, $datos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $solicitudId = (string) ($datos['solicitud_id'] ?? '');
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            if ($solicitudId !== '' && (string) ($json['cancelacion_nacional']['solicitud_id'] ?? '') === $solicitudId) {
                return $pedido->fresh();
            }
            if (isset($datos['revision_esperada'], $datos['base_captura'])) {
                RevisionCapturaPedido::comprobar($pedido, $datos);
            }

            $liberar = (bool) ($datos['liberar_reserva'] ?? false);
            $avisado = (bool) ($datos['cedis_notificado'] ?? false);
            if ($liberar && ! $avisado) {
                throw new RuntimeException('CEDIS no ha confirmado la recepción. La reserva no se libera.');
            }
            if ($liberar && ! $pedido->puedeCancelarDirecto()) {
                throw new RuntimeException('El flujo actual no libera esta reserva.');
            }

            $motivo = trim((string) ($datos['motivo'] ?? ''));
            if ($motivo === '') {
                throw new \InvalidArgumentException('Indique el motivo.');
            }
            $json['cancelacion_nacional'] = [
                'solicitud_id' => $solicitudId,
                'solicitud' => [
                    'motivo' => $motivo,
                    'usuario_id' => $usuario->id,
                    'at' => now()->toIso8601String(),
                ],
                'mercancia' => [
                    'estado' => $liberar ? 'liberada' : 'sin_liberar',
                    'usuario_id' => $usuario->id,
                ],
                'pagos' => [
                    'estado' => 'conservados',
                    'total_mercancia' => $pedido->total_mercancia,
                ],
                'guia' => [
                    'motivo' => filled($pedido->numero_rastreo) ? $motivo : null,
                    'usuario_id' => filled($pedido->numero_rastreo) ? $usuario->id : null,
                    'absorbido_por' => null,
                    'cancelacion_paqueteria_confirmada' => false,
                ],
            ];
            $pedido->update(['solicitud_preparacion_json' => $json]);

            if (! $liberar) {
                return $pedido->fresh('estatus');
            }

            $cancelado = $this->cancelar->ejecutar($pedido->fresh(), $usuario->id, [
                'motivo' => 'cliente_desiste',
                'comentario' => $motivo,
                'resolucion_financiera' => CancelarPedidoBmaService::RESOLUCION_PENDIENTE,
            ]);
            $despues = is_array($cancelado->solicitud_preparacion_json) ? $cancelado->solicitud_preparacion_json : [];
            $despues['cancelacion_nacional'] = $json['cancelacion_nacional'];
            $cancelado->update(['solicitud_preparacion_json' => $despues]);

            return $cancelado->fresh('estatus');
        });
    }
}
