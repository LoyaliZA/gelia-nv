<?php

namespace App\Support\Reportes;

use App\Models\CatalogoBanco;
use Illuminate\Support\Carbon;

final class TituloReportePagosPedidos
{
    /** @param  array<string, mixed>  $filtros */
    public static function desdeFiltros(array $filtros): string
    {
        if (($filtros['tipo_reporte'] ?? 'pedido') === 'vouchers') {
            $partes = ['Vouchers'];
        } else {
            $partes = ['Pagos'];
        }

        $bancoIds = $filtros['banco_ids'] ?? [];
        if (count($bancoIds) === 1) {
            $nombre = CatalogoBanco::query()->whereKey($bancoIds[0])->value('nombre');
            if ($nombre) {
                $partes[] = $nombre;
            }
        } elseif (! empty($filtros['sin_banco'])) {
            $partes[] = 'sin banco';
        }

        $desde = $filtros['fecha_validacion_desde']
            ?? $filtros['fecha_pedido_desde']
            ?? $filtros['fecha_reportada_desde']
            ?? $filtros['fecha_pago_desde']
            ?? null;
        $hasta = $filtros['fecha_validacion_hasta']
            ?? $filtros['fecha_pedido_hasta']
            ?? $filtros['fecha_reportada_hasta']
            ?? $filtros['fecha_pago_hasta']
            ?? null;

        if ($desde) {
            $partes[] = Carbon::parse($desde)->locale('es')->isoFormat('MMM YYYY');
        } elseif ($hasta) {
            $partes[] = Carbon::parse($hasta)->locale('es')->isoFormat('MMM YYYY');
        }

        if (count($partes) === 1 && ! empty($filtros['busqueda'])) {
            $partes[] = mb_substr(trim((string) $filtros['busqueda']), 0, 40);
        }

        return implode(' ', $partes);
    }
}
