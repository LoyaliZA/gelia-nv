<?php

namespace App\Support\Reportes;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Support\Reportes\FechasPagoReporte;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/** Anexo A — vouchers incrustados y remisiones referenciadas. */
final class AnexoEvidenciasReportePagosPedidosPdf
{
    private const ALTURA_COMPLETA = 1200;

    private const RATIO_COMPLETA = 1.55;

    /**
     * @param  Collection<int, PedidoBmaCierrePago>  $cierres
     * @param  array<string, mixed>  $params
     * @param  callable(PedidoBmaCierrePagoItem): ?string  $imagenPath
     * @return array{
     *     titulo: string,
     *     paginas: list<array{tipo: string, vouchers: list<array<string, mixed>>}>,
     *     remisiones: list<array<string, string>>,
     *     vacio: bool
     * }
     */
    public static function presentar(Collection $cierres, array $params, callable $imagenPath): array
    {
        $incluirVouchers = ($params['incluir_vouchers'] ?? true) !== false;
        $incluirRemisiones = ($params['incluir_referencias_remision'] ?? true) !== false;
        $incluirRechazadas = ($params['incluir_evidencias_rechazadas_sustituidas'] ?? true) !== false;
        $incluirRemisionesCompletas = ! empty($params['incluir_remisiones_completas']);

        $vouchers = [];
        $remisiones = [];
        $remisionesEmbebidas = [];

        foreach ($cierres as $cierre) {
            if ($incluirRemisiones) {
                $remision = self::remisionReferencia($cierre);
                if ($remision !== null) {
                    $remisiones[$cierre->id] = $remision;
                }
            }

            if ($incluirRemisionesCompletas) {
                $embebida = self::remisionEmbebida($cierre);
                if ($embebida !== null) {
                    $remisionesEmbebidas[] = $embebida;
                }
            }

            if (! $incluirVouchers) {
                continue;
            }

            foreach (AlcanceExhibicionesReportePagosPedidos::filtrar($cierre->items, $params) as $item) {
                if (! AlcanceExhibicionesReportePagosPedidos::itemTieneVoucher($item)) {
                    continue;
                }

                if (! $incluirRechazadas) {
                    $estado = $item->estado_revision_snapshot;
                    $sustituido = ! $item->activo_para_cobertura_snapshot
                        && PedidoBmaCierrePagoItem::query()
                            ->where('pedido_bma_cierre_pago_id', $item->pedido_bma_cierre_pago_id)
                            ->where('reemplaza_pago_id', $item->pedido_bma_pago_id)
                            ->exists();
                    if ($estado === 'rechazado' || $sustituido) {
                        continue;
                    }
                }

                $path = $imagenPath($item);
                $vouchers[] = [
                    'layout' => self::layoutImagen($path),
                    'imagen_path' => $path,
                    'es_pdf' => $path === null && $item->mime_type_snapshot === 'application/pdf',
                    'nombre_archivo' => $item->nombre_archivo_snapshot,
                    'encabezado' => self::encabezadoVoucher($cierre, $item),
                ];
            }
        }

        return [
            'titulo' => 'Anexo A — Vouchers y comprobantes',
            'paginas' => $incluirVouchers ? self::agruparPaginas($vouchers) : [],
            'remisiones' => $incluirRemisiones && ! $incluirRemisionesCompletas ? array_values($remisiones) : [],
            'remisiones_embebidas' => $incluirRemisionesCompletas ? $remisionesEmbebidas : [],
            'vacio' => count($vouchers) === 0 && count($remisiones) === 0 && count($remisionesEmbebidas) === 0,
        ];
    }

    /**
     * @return list<array{tipo: string, vouchers: list<array<string, mixed>>}>
     */
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

    private static function encabezadoVoucher(
        PedidoBmaCierrePago $cierre,
        PedidoBmaCierrePagoItem $item,
    ): array {
        return [
            ['label' => 'Folio del pedido', 'valor' => $cierre->folio_snapshot ?: '—'],
            ['label' => 'Exhibición', 'valor' => '#'.$item->numero_exhibicion],
            ['label' => 'Monto', 'valor' => self::fmtMxn((float) $item->monto_snapshot)],
            ['label' => 'Banco', 'valor' => $item->banco_snapshot ?: '—'],
            ['label' => 'Fecha del movimiento', 'valor' => FechasPagoReporte::formatear($item->fecha_pago_snapshot)],
            ['label' => 'Fecha reportada', 'valor' => FechasPagoReporte::formatearFechaHora($item->capturado_at_snapshot)],
            ['label' => 'Estado', 'valor' => self::labelEstado($item->estado_revision_snapshot)],
        ];
    }

    /** @return ?array{folio_pedido: string, folio_remision: string, nombre: string, pdf_path: string} */
    private static function remisionEmbebida(PedidoBmaCierrePago $cierre): ?array
    {
        $meta = $cierre->metadata_snapshot ?? [];
        if (empty($meta['remision_documento_id']) && empty($cierre->folio_remision_snapshot)) {
            return null;
        }

        $remisionDoc = $cierre->pedido?->remision->first();
        $ruta = $remisionDoc?->ruta_archivo;
        if (! $ruta || ! Storage::disk('public')->exists($ruta)) {
            return null;
        }

        $abs = Storage::disk('public')->path($ruta);
        if (! is_readable($abs)) {
            return null;
        }

        return [
            'folio_pedido' => $cierre->folio_snapshot ?: '—',
            'folio_remision' => $cierre->folio_remision_snapshot ?: '—',
            'nombre' => $meta['remision_nombre'] ?? ($remisionDoc?->nombre_original ?? '—'),
            'pdf_path' => $abs,
        ];
    }

    /** @return ?array{folio_pedido: string, folio_remision: string, nombre: string, fecha: string} */
    private static function remisionReferencia(PedidoBmaCierrePago $cierre): ?array
    {
        $meta = $cierre->metadata_snapshot ?? [];
        if (empty($meta['remision_documento_id']) && empty($cierre->folio_remision_snapshot)) {
            return null;
        }

        $remisionDoc = $cierre->pedido?->remision->first();

        return [
            'folio_pedido' => $cierre->folio_snapshot ?: '—',
            'folio_remision' => $cierre->folio_remision_snapshot ?: '—',
            'nombre' => $meta['remision_nombre'] ?? ($remisionDoc?->nombre_original ?? '—'),
            'fecha' => FechasPagoReporte::formatear($remisionDoc?->created_at),
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

        if ($h >= self::ALTURA_COMPLETA || ($h / $w) >= self::RATIO_COMPLETA) {
            return 'completo';
        }

        return 'medio';
    }

    private static function labelEstado(?string $estado): string
    {
        $map = [
            'pendiente' => 'Pendiente',
            'en_revision' => 'En revisión',
            'verificado' => 'Verificado',
            'con_observaciones' => 'Con observaciones',
            'rechazado' => 'Rechazado',
            'confirmado' => 'Verificado',
            'con_diferencia' => 'Con observaciones',
        ];

        if ($estado === null || $estado === '') {
            return '—';
        }

        return $map[$estado] ?? $estado;
    }

    private static function fmtMxn(float $monto): string
    {
        return '$'.number_format($monto, 2, '.', ',');
    }
}
