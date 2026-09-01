<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\User;

class ResolverAccesoCierreReportePagosService
{
    public function cierre(User $usuario, int|string $cierreId): PedidoBmaCierrePago
    {
        return PedidoBmaCierrePago::query()
            ->with(['pedido.estatus', 'items', 'vendedor'])
            ->findOrFail($cierreId);
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
}
