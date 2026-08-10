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
    ) {}

    public function handle(PedidoBmaPago $pago, string $estadoRevision, ?int $usuarioId = null, ?string $observaciones = null): PedidoBmaPago
    {
        if (! in_array($estadoRevision, PedidoBmaPago::ESTADOS_REVISION, true)) {
            throw new InvalidArgumentException('Estado de revisión no válido.');
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
