<?php

namespace App\Support\Reportes;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Estados y resumen de confirmación administrativa del reporte de pagos. */
final class AdminEstadoReportePagosPedidos
{
    public const PENDIENTE = 'pendiente';

    public const CONFIRMADO = 'confirmado';

    public const CON_ERROR = 'con_error';

    public const ESTADOS = [
        self::PENDIENTE,
        self::CONFIRMADO,
        self::CON_ERROR,
    ];

    public static function label(?string $estado): string
    {
        return match ($estado) {
            self::CONFIRMADO => 'Confirmado por Administración',
            self::CON_ERROR => 'Error reportado',
            self::PENDIENTE => 'Pendiente de revisión admin',
            default => '—',
        };
    }

    /** @param  Collection<int, PedidoBmaCierrePagoItem>  $items */
    public static function resumenPedido(PedidoBmaCierrePago $cierre, Collection $items): string
    {
        if ($cierre->admin_pedido_error_reportado_at !== null) {
            return self::CON_ERROR;
        }

        if ($items->isEmpty()) {
            return self::PENDIENTE;
        }

        $estados = $items->pluck('admin_estado')->unique()->values();

        if ($estados->contains(self::CON_ERROR)) {
            return self::CON_ERROR;
        }

        if ($estados->every(fn (string $e) => $e === self::CONFIRMADO)) {
            return self::CONFIRMADO;
        }

        if ($estados->contains(self::CONFIRMADO)) {
            return 'parcial';
        }

        return self::PENDIENTE;
    }

    /** @return array<string, mixed> */
    public static function payloadItem(PedidoBmaCierrePagoItem $item): array
    {
        return [
            'id' => $item->id,
            'admin_estado' => $item->admin_estado ?? self::PENDIENTE,
            'admin_estado_label' => self::label($item->admin_estado),
            'admin_confirmado_at' => $item->admin_confirmado_at?->toIso8601String(),
            'admin_confirmado_por' => $item->adminConfirmadoPor?->only(['id', 'name']),
            'admin_error_comentario' => $item->admin_error_comentario,
            'admin_error_reportado_at' => $item->admin_error_reportado_at?->toIso8601String(),
            'admin_error_reportado_por' => $item->adminErrorReportadoPor?->only(['id', 'name']),
            'admin_error_evidencia_url' => $item->admin_error_evidencia_ruta
                ? '/storage/'.$item->admin_error_evidencia_ruta
                : null,
            'admin_error_evidencia_nombre' => $item->admin_error_evidencia_nombre,
        ];
    }

    /** @return array<string, mixed> */
    public static function payloadCierre(PedidoBmaCierrePago $cierre): array
    {
        $items = $cierre->relationLoaded('items') ? $cierre->items : $cierre->items()->get();

        return [
            'admin_resumen' => self::resumenPedido($cierre, $items),
            'admin_resumen_label' => self::labelResumen(self::resumenPedido($cierre, $items)),
            'admin_pedido_error_comentario' => $cierre->admin_pedido_error_comentario,
            'admin_pedido_error_reportado_at' => $cierre->admin_pedido_error_reportado_at?->toIso8601String(),
            'admin_pedido_error_reportado_por' => $cierre->adminPedidoErrorReportadoPor?->only(['id', 'name']),
            'admin_pedido_error_evidencia_url' => $cierre->admin_pedido_error_evidencia_ruta
                ? '/storage/'.$cierre->admin_pedido_error_evidencia_ruta
                : null,
            'admin_pedido_error_evidencia_nombre' => $cierre->admin_pedido_error_evidencia_nombre,
        ];
    }

    public static function labelResumen(string $resumen): string
    {
        return match ($resumen) {
            self::CONFIRMADO => 'Confirmado por Administración',
            self::CON_ERROR => 'Con error reportado',
            'parcial' => 'Confirmación parcial',
            self::PENDIENTE => 'Pendiente de revisión admin',
            default => '—',
        };
    }

    /** @param  Builder<PedidoBmaCierrePago>  $query */
    public static function aplicarFiltroCierre(Builder $query, ?string $estadoAdmin): void
    {
        if ($estadoAdmin === null || $estadoAdmin === '' || $estadoAdmin === 'todos') {
            return;
        }

        if ($estadoAdmin === self::PENDIENTE) {
            $query->whereNull('admin_pedido_error_reportado_at')
                ->whereHas('items', fn (Builder $q) => $q->where('admin_estado', self::PENDIENTE));

            return;
        }

        if ($estadoAdmin === self::CONFIRMADO) {
            $query->whereNull('admin_pedido_error_reportado_at')
                ->whereHas('items')
                ->whereDoesntHave('items', fn (Builder $q) => $q->whereIn('admin_estado', [self::PENDIENTE, self::CON_ERROR]));

            return;
        }

        if ($estadoAdmin === self::CON_ERROR) {
            $query->where(function (Builder $q) {
                $q->whereNotNull('admin_pedido_error_reportado_at')
                    ->orWhereHas('items', fn (Builder $i) => $i->where('admin_estado', self::CON_ERROR));
            });
        }
    }

    /** @param  Builder<PedidoBmaCierrePagoItem>  $query */
    public static function aplicarFiltroItem(Builder $query, ?string $estadoAdmin): void
    {
        if ($estadoAdmin === null || $estadoAdmin === '' || $estadoAdmin === 'todos') {
            return;
        }

        if ($estadoAdmin === self::PENDIENTE) {
            $query->where('admin_estado', self::PENDIENTE)
                ->whereHas('cierre', fn (Builder $q) => $q->whereNull('admin_pedido_error_reportado_at'));

            return;
        }

        if ($estadoAdmin === self::CON_ERROR) {
            $query->where(function (Builder $q) {
                $q->where('admin_estado', self::CON_ERROR)
                    ->orWhereHas('cierre', fn (Builder $c) => $c->whereNotNull('admin_pedido_error_reportado_at'));
            });

            return;
        }

        $query->where('admin_estado', $estadoAdmin);
    }
}
