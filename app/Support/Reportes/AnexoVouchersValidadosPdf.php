<?php

namespace App\Support\Reportes;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;

/** Anexo de vouchers para el PDF de vouchers validados. */
final class AnexoVouchersValidadosPdf
{
    /**
     * @param  list<PedidoBmaCierrePagoItem>  $items
     * @param  callable(PedidoBmaCierrePagoItem): ?string  $imagenPath
     * @return array{titulo: string, paginas: list<array>, vacio: bool}
     */
    public static function presentar(array $items, callable $imagenPath): array
    {
        $vouchers = [];

        foreach ($items as $item) {
            if (! AlcanceExhibicionesReportePagosPedidos::itemTieneVoucher($item)) {
                continue;
            }

            $cierre = $item->cierre;
            if (! $cierre) {
                continue;
            }

            $path = $imagenPath($item);
            $vouchers[] = [
                'layout' => self::layoutImagen($path),
                'imagen_path' => $path,
                'es_pdf' => $path === null && $item->mime_type_snapshot === 'application/pdf',
                'nombre_archivo' => $item->nombre_archivo_snapshot,
                'encabezado' => self::encabezado($cierre, $item),
            ];
        }

        return [
            'titulo' => 'Anexo — Comprobantes de pago',
            'paginas' => self::agruparPaginas($vouchers),
            'remisiones' => [],
            'vacio' => count($vouchers) === 0,
        ];
    }

    /** @return list<array{tipo: string, vouchers: list<array>}> */
    private static function agruparPaginas(array $vouchers): array
    {
        $paginas = [];
        $buffer = [];

        foreach ($vouchers as $voucher) {
            if ($voucher['layout'] === 'completo') {
                if ($buffer !== []) {
                    $paginas[] = ['tipo' => 'doble', 'vouchers' => $buffer];
                    $buffer = [];
                }
                $paginas[] = ['tipo' => 'completo', 'vouchers' => [$voucher]];

                continue;
            }

            $buffer[] = $voucher;
            if (count($buffer) === 2) {
                $paginas[] = ['tipo' => 'doble', 'vouchers' => $buffer];
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $paginas[] = ['tipo' => 'doble', 'vouchers' => $buffer];
        }

        return $paginas;
    }

    /** @return list<array{label: string, valor: string}> */
    private static function encabezado(PedidoBmaCierrePago $cierre, PedidoBmaCierrePagoItem $item): array
    {
        return [
            ['label' => 'Pedido', 'valor' => $cierre->folio_snapshot ?: '—'],
            ['label' => 'Exhibición', 'valor' => '#'.$item->numero_exhibicion],
            ['label' => 'Monto', 'valor' => '$'.number_format((float) $item->monto_snapshot, 2, '.', ',')],
            ['label' => 'Banco', 'valor' => $item->banco_snapshot ?: '—'],
            ['label' => 'Referencia', 'valor' => $item->referencia_snapshot ?: '—'],
            ['label' => 'Movimiento', 'valor' => FechasPagoReporte::formatear($item->fecha_pago_snapshot)],
            ['label' => 'Reporte', 'valor' => FechasPagoReporte::formatearFechaHora($item->capturado_at_snapshot)],
            ['label' => 'Estado', 'valor' => self::labelEstado((string) ($item->estado_revision_snapshot ?? ''))],
        ];
    }

    private static function layoutImagen(?string $path): string
    {
        if ($path === null || ! is_readable($path)) {
            return 'completo';
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return 'completo';
        }

        [$w, $h] = $info;
        if ($w <= 0) {
            return 'completo';
        }

        if ($h >= 1200 || ($h / $w) >= 1.55) {
            return 'completo';
        }

        return 'medio';
    }

    private static function labelEstado(string $estado): string
    {
        $map = [
            'pendiente' => 'Pendiente',
            'en_revision' => 'En revisión',
            'verificado' => 'Validado',
            'con_observaciones' => 'Con observaciones',
            'rechazado' => 'Rechazado',
        ];

        return $map[$estado] ?? ($estado !== '' ? $estado : '—');
    }
}
