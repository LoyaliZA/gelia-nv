<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\User;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Auth\Access\AuthorizationException;

class ResolverAccesoCierreReportePagosService
{
    public function cierre(User $usuario, int|string $cierreId): PedidoBmaCierrePago
    {
        $cierre = PedidoBmaCierrePago::query()
            ->with(['pedido.estatus', 'items', 'vendedor'])
            ->findOrFail($cierreId);

        $this->autorizar($usuario, $cierre);

        return $cierre;
    }

    public function item(User $usuario, int|string $cierreId, int|string $itemId): PedidoBmaCierrePagoItem
    {
        $cierre = $this->cierre($usuario, $cierreId);
        $item = $cierre->items->firstWhere('id', (int) $itemId)
            ?? PedidoBmaCierrePagoItem::query()
                ->where('pedido_bma_cierre_pago_id', $cierre->id)
                ->findOrFail($itemId);

        return $item;
    }

    private function autorizar(User $usuario, PedidoBmaCierrePago $cierre): void
    {
        if ($cierre->pedido && ! VisibilidadPedidoBma::puedeConsultar($usuario, $cierre->pedido)) {
            throw new AuthorizationException('No tiene acceso a este cierre.');
        }
    }
}
