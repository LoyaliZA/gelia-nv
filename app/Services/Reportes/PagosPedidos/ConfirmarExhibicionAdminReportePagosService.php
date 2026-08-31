<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\Reportes\AdminEstadoReportePagosPedidos;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConfirmarExhibicionAdminReportePagosService
{
    public function __construct(
        private ResolverAccesoCierreReportePagosService $acceso,
        private RegistrarHistorialPedidoService $historial,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(User $usuario, int|string $cierreId, int|string $itemId): array
    {
        $item = $this->acceso->item($usuario, $cierreId, $itemId);
        $cierre = $item->cierre()->with('pedido.estatus')->firstOrFail();

        if ($item->admin_estado === AdminEstadoReportePagosPedidos::CON_ERROR) {
            throw new InvalidArgumentException('No se puede confirmar una exhibición con error reportado.');
        }

        if ($cierre->tieneErrorAdminPedido()) {
            throw new InvalidArgumentException('El pedido tiene un error administrativo reportado.');
        }

        if ($item->admin_estado === AdminEstadoReportePagosPedidos::CONFIRMADO) {
            return $this->payload($item->fresh(['adminConfirmadoPor', 'cierre.items.adminConfirmadoPor', 'cierre.adminPedidoErrorReportadoPor']));
        }

        return DB::transaction(function () use ($usuario, $item, $cierre) {
            $item->update([
                'admin_estado' => AdminEstadoReportePagosPedidos::CONFIRMADO,
                'admin_confirmado_por_id' => $usuario->id,
                'admin_confirmado_at' => now(),
            ]);

            $pedido = $cierre->pedido;
            if ($pedido?->catalogo_estatus_pedido_id) {
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $pedido->catalogo_estatus_pedido_id,
                    $pedido->catalogo_estatus_pedido_id,
                    $this->comentarioExhibicion($cierre->folio_snapshot, $item),
                    AccionesHistorialPedidoBma::CONFIRMACION_ADMIN_EXHIBICION,
                );
            }

            $item = $item->fresh(['adminConfirmadoPor', 'cierre.items.adminConfirmadoPor', 'cierre.adminPedidoErrorReportadoPor']);

            return $this->payload($item);
        });
    }

    /** @return array<string, mixed> */
    private function payload(PedidoBmaCierrePagoItem $item): array
    {
        $cierre = $item->cierre;
        $cierre?->loadMissing(['items.adminConfirmadoPor', 'adminPedidoErrorReportadoPor']);

        return [
            'item' => AdminEstadoReportePagosPedidos::payloadItem($item),
            'cierre' => $cierre ? AdminEstadoReportePagosPedidos::payloadCierre($cierre) : null,
        ];
    }

    private function comentarioExhibicion(?string $folio, PedidoBmaCierrePagoItem $item): string
    {
        $monto = number_format((float) $item->monto_snapshot, 2, '.', ',');

        return sprintf(
            'Administración confirmó exhibición #%d del pedido %s (%s · $%s).',
            $item->numero_exhibicion,
            $folio ?: '—',
            $item->banco_snapshot ?: 'Sin banco',
            $monto,
        );
    }
}
