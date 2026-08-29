<?php

namespace App\Support\Reportes;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Metadatos del encabezado administrativo del PDF de pagos de pedidos.
 */
final class EncabezadoReportePagosPedidosPdf
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function construir(User $usuario, array $filtros, int $totalPedidos): array
    {
        $tipoFecha = self::tipoFecha($filtros);
        $estadoCierre = $filtros['estado_cierre'] ?? 'vigente';

        return [
            'folio' => 'RPP-'.now()->format('Ymd-His').'-'.strtoupper(substr(uniqid(), -4)),
            'titulo' => 'Reporte administrativo de pagos de pedidos',
            'generado_at' => self::fmtFechaHora(now()),
            'usuario' => $usuario->name,
            'periodo' => self::periodoConsultado($filtros, $tipoFecha),
            'tipo_fecha' => $tipoFecha,
            'estado_reporte' => $estadoCierre === 'vigente' ? 'Definitivo' : 'Histórico reconstruido',
            'total_pedidos' => $totalPedidos,
            'color_primario' => '#ec4899',
            'color_franja' => '#fdf2f8',
        ];
    }

    /** @param  array<string, mixed>  $filtros */
    public static function tipoFechaPublico(array $filtros): string
    {
        return self::tipoFecha($filtros);
    }

    /** @param  array<string, mixed>  $filtros */
    private static function tipoFecha(array $filtros): string
    {
        return match ($filtros['tipo_fecha'] ?? null) {
            'pedido' => 'Fecha del pedido',
            'reportada' => 'Fecha reportada',
            'pago' => 'Fecha del pago',
            default => 'Fecha de validación',
        };
    }

    /** @param  array<string, mixed>  $filtros */
    private static function periodoConsultado(array $filtros, string $tipoFecha): string
    {
        $map = [
            'Fecha del pedido' => ['fecha_pedido_desde', 'fecha_pedido_hasta'],
            'Fecha reportada' => ['fecha_reportada_desde', 'fecha_reportada_hasta'],
            'Fecha del pago' => ['fecha_pago_desde', 'fecha_pago_hasta'],
            'Fecha de validación' => ['fecha_validacion_desde', 'fecha_validacion_hasta'],
        ];
        [$desdeKey, $hastaKey] = $map[$tipoFecha] ?? $map['Fecha de validación'];
        $desde = $filtros[$desdeKey] ?? null;
        $hasta = $filtros[$hastaKey] ?? null;

        if ($desde && $hasta) {
            return self::fmtFecha($desde).' — '.self::fmtFecha($hasta);
        }
        if ($desde) {
            return 'Desde '.self::fmtFecha($desde);
        }
        if ($hasta) {
            return 'Hasta '.self::fmtFecha($hasta);
        }

        return 'Sin rango de fechas (consulta abierta)';
    }

    private static function fmtFecha(mixed $value): string
    {
        return ucfirst(Carbon::parse($value)->locale('es')->isoFormat('D MMM YYYY'));
    }

    private static function fmtFechaHora(Carbon $dt): string
    {
        return $dt->locale('es')->isoFormat('D MMM YYYY, HH:mm');
    }
}
