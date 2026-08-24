<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use InvalidArgumentException;

class RevisarPagoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private RechazarPagosPedidoBmaService $rechazarPagos,
    ) {}

    public function handle(PedidoBmaPago $pago, string $estadoRevision, ?int $usuarioId = null, ?string $observaciones = null): PedidoBmaPago
    {
        if (! in_array($estadoRevision, PedidoBmaPago::ESTADOS_REVISION, true)) {
            throw new InvalidArgumentException('Estado de revisión no válido.');
        }

        if ($estadoRevision === PedidoBmaPago::REVISION_RECHAZADO) {
            if (! filled($observaciones)) {
                throw new InvalidArgumentException('Debe indicar observaciones para este estado.');
            }
            $pedido = $pago->pedido;
            if (! $pedido || ! $usuarioId) {
                throw new InvalidArgumentException('No se puede rechazar sin pedido o usuario.');
            }
            $this->rechazarPagos->ejecutar($pedido, [$pago->id], (string) $observaciones, $usuarioId);

            return $pago->fresh(['banco']);
        }

        if ($estadoRevision === PedidoBmaPago::REVISION_CON_OBSERVACIONES && ! filled($observaciones)) {
            throw new InvalidArgumentException('Debe indicar observaciones para este estado.');
        }

        if (! $pago->activo_para_cobertura) {
            throw new InvalidArgumentException('No se puede revisar una exhibición inactiva para cobertura.');
        }

        $anterior = $pago->estado_revision;
        $pago->update([
            'estado_revision' => $estadoRevision,
            'observaciones' => $observaciones ?? $pago->observaciones,
            'revisado_por_id' => $usuarioId,
            'revisado_at' => now(),
        ]);

        $pedido = $pago->pedido;
        if ($pedido && $usuarioId) {
            $this->historial->ejecutar(
                $pedido->id,
                $usuarioId,
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                sprintf(
                    'Exhibición #%d: revisión %s → %s.',
                    $pago->numero_exhibicion,
                    $anterior,
                    $estadoRevision
                ),
                AccionesHistorialPedidoBma::REVISION_EXHIBICION_PAGO
            );
        }

        return $pago->fresh(['banco']);
    }
}
