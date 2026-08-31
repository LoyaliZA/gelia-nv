<?php

namespace App\Support\Reportes;

use App\Models\Reportes\PedidoBmaCierrePagoItem;
use Illuminate\Support\Collection;

/** ponytail: bucket banco+referencia+monto+día; upgrade path = índice DB o ventana por pedido. */
final class DetectarPosiblesDuplicadosVouchersService
{
    /**
     * @param  iterable<PedidoBmaCierrePagoItem>  $items
     * @return array<int, true> ids de items marcados como posible duplicado
     */
    public function marcar(iterable $items): array
    {
        $coleccion = $items instanceof Collection ? $items : collect([...$items]);
        /** @var array<string, list<int>> $buckets */
        $buckets = [];

        foreach ($coleccion as $item) {
            if (! $item instanceof PedidoBmaCierrePagoItem) {
                continue;
            }
            $clave = $this->clave($item);
            if ($clave === null) {
                continue;
            }
            $buckets[$clave][] = $item->id;
        }

        $marcados = [];
        foreach ($buckets as $ids) {
            if (count($ids) < 2) {
                continue;
            }
            foreach ($ids as $id) {
                $marcados[$id] = true;
            }
        }

        return $marcados;
    }

    private function clave(PedidoBmaCierrePagoItem $item): ?string
    {
        $referencia = trim((string) ($item->referencia_snapshot ?? ''));
        $banco = (string) ($item->catalogo_banco_id ?? $item->banco_snapshot ?? '');
        $monto = number_format((float) $item->monto_snapshot, 2, '.', '');
        $dia = $item->fecha_pago_snapshot?->toDateString();

        if ($referencia === '' || $banco === '' || $dia === null) {
            return null;
        }

        return strtolower($banco).'|'.strtolower($referencia).'|'.$monto.'|'.$dia;
    }
}
