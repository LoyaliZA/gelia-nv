<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\Reportes\AdminEstadoReportePagosPedidos;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConfirmarPedidoAdminReportePagosService
{
    public function __construct(
        private ResolverAccesoCierreReportePagosService $acceso,
        private RegistrarHistorialPedidoService $historial,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(User $usuario, int|string $cierreId): array
    {
        $cierre = $this->acceso->cierre($usuario, $cierreId);
        $cierre->load(['items', 'pedido.estatus']);

        if ($cierre->tieneErrorAdminPedido()) {
            throw new InvalidArgumentException('El pedido tiene un error administrativo reportado.');
        }

        $pendientes = $cierre->items->where('admin_estado', AdminEstadoReportePagosPedidos::PENDIENTE);

        if ($pendientes->isEmpty()) {
            return $this->payload($cierre->fresh(['items.adminConfirmadoPor', 'adminPedidoErrorReportadoPor']));
        }

        return DB::transaction(function () use ($usuario, $cierre, $pendientes) {
            $now = now();
            foreach ($pendientes as $item) {
                $item->update([
                    'admin_estado' => AdminEstadoReportePagosPedidos::CONFIRMADO,
                    'admin_confirmado_por_id' => $usuario->id,
                    'admin_confirmado_at' => $now,
                ]);
            }

            $pedido = $cierre->pedido;
            if ($pedido?->catalogo_estatus_pedido_id) {
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $pedido->catalogo_estatus_pedido_id,
                    $pedido->catalogo_estatus_pedido_id,
                    sprintf(
                        'Administración confirmó el pedido %s (%d exhibición(es) pendiente(s)).',
                        $cierre->folio_snapshot ?: '—',
                        $pendientes->count(),
                    ),
                    AccionesHistorialPedidoBma::CONFIRMACION_ADMIN_PEDIDO,
                );
            }

            return $this->payload($cierre->fresh(['items.adminConfirmadoPor', 'adminPedidoErrorReportadoPor']));
        });
    }

    /** @return array<string, mixed> */
    private function payload(PedidoBmaCierrePago $cierre): array
    {
        return [
            'cierre' => AdminEstadoReportePagosPedidos::payloadCierre($cierre),
            'items' => $cierre->items->map(fn ($item) => AdminEstadoReportePagosPedidos::payloadItem($item))->values()->all(),
        ];
    }
}
