<?php

namespace App\Support\Reportes;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Semántica de las cuatro fechas independientes en reportes de pagos. */
final class FechasPagoReporte
{
    public const SIN_INFORMACION = 'Sin información';

    public const CLAVE_SIN_FECHA = '__sin_fecha__';

    public const TIPO_PEDIDO = 'pedido';

    public const TIPO_PAGO = 'pago';

    public const TIPO_REPORTADA = 'reportada';

    public const TIPO_VALIDACION = 'validacion';

    /** @var list<string> */
    public const TIPOS = [
        self::TIPO_PEDIDO,
        self::TIPO_PAGO,
        self::TIPO_REPORTADA,
        self::TIPO_VALIDACION,
    ];

    public static function formatear(?Carbon $fecha): string
    {
        if ($fecha === null) {
            return self::SIN_INFORMACION;
        }

        return $fecha->copy()->locale('es')->isoFormat('D MMM YYYY');
    }

    public static function formatearFechaHora(?Carbon $fecha): string
    {
        if ($fecha === null) {
            return self::SIN_INFORMACION;
        }

        return $fecha->copy()->locale('es')->isoFormat('D MMM YYYY, HH:mm');
    }

    public static function iso8601(?Carbon $fecha): ?string
    {
        return $fecha?->toIso8601String();
    }

    public static function diaCalendario(?Carbon $fecha): ?string
    {
        return $fecha?->toDateString();
    }

    /** El voucher se reportó en un día distinto al del movimiento bancario declarado. */
    public static function reportadoPosteriormente(?Carbon $fechaPago, ?Carbon $reportadaAt): bool
    {
        if ($fechaPago === null || $reportadaAt === null) {
            return false;
        }

        return $reportadaAt->toDateString() > $fechaPago->toDateString();
    }

    public static function claveAgrupamientoPedido(PedidoBmaCierrePago $cierre): string
    {
        return $cierre->pedido_fecha?->toDateString() ?? self::CLAVE_SIN_FECHA;
    }

    public static function etiquetaGrupoPedido(string $clave): string
    {
        if ($clave === self::CLAVE_SIN_FECHA) {
            return self::SIN_INFORMACION.' (fecha del pedido)';
        }

        return ucfirst(Carbon::parse($clave)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY'));
    }

    /**
     * @return array{
     *     pedido: ?string,
     *     pago: ?string,
     *     reportada: ?string,
     *     validacion: ?string,
     *     pedido_label: string,
     *     pago_label: string,
     *     reportada_label: string,
     *     validacion_label: string,
     *     reportado_posteriormente: bool
     * }
     */
    public static function exhibicion(PedidoBmaCierrePagoItem $item, PedidoBmaCierrePago $cierre): array
    {
        $pedido = $cierre->pedido_fecha;
        $pago = $item->fecha_pago_snapshot;
        $reportada = $item->capturado_at_snapshot;
        $validacion = $cierre->validado_at;

        return [
            'pedido' => self::iso8601($pedido),
            'pago' => self::iso8601($pago),
            'reportada' => self::iso8601($reportada),
            'validacion' => self::iso8601($validacion),
            'pedido_label' => self::formatear($pedido),
            'pago_label' => self::formatear($pago),
            'reportada_label' => self::formatearFechaHora($reportada),
            'validacion_label' => self::formatearFechaHora($validacion),
            'reportado_posteriormente' => self::reportadoPosteriormente($pago, $reportada),
        ];
    }

    /** @param  Builder<PedidoBmaCierrePago>  $query */
    public static function aplicarFiltroIncompleta(Builder $query, string $tipo): void
    {
        if (! in_array($tipo, self::TIPOS, true)) {
            return;
        }

        match ($tipo) {
            self::TIPO_PEDIDO => $query->whereNull('pedido_fecha'),
            self::TIPO_VALIDACION => $query->whereNull('validado_at'),
            self::TIPO_REPORTADA => $query->whereHas('items', fn (Builder $q) => $q->whereNull('capturado_at_snapshot')),
            self::TIPO_PAGO => $query->whereHas('items', fn (Builder $q) => $q->whereNull('fecha_pago_snapshot')),
        };
    }

    /** Campo de agrupamiento principal según el tipo de reporte (fase 2). */
    public static function campoAgrupamientoListado(string $reporte = self::TIPO_PEDIDO): string
    {
        return match ($reporte) {
            self::TIPO_PAGO => 'fecha_pago_snapshot',
            default => 'pedido_fecha',
        };
    }
}
