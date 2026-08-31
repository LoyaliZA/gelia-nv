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
    public static function conteoExhibicionesAdmin(Collection $items): array
    {
        $total = $items->count();
        $revisadas = $items->filter(
            fn (PedidoBmaCierrePagoItem $item) => in_array(
                $item->admin_estado,
                [self::CONFIRMADO, self::CON_ERROR],
                true,
            ),
        )->count();

        return [
            'admin_exhibiciones_total' => $total,
            'admin_exhibiciones_revisadas' => $revisadas,
            'admin_exhibiciones_pendientes' => $items
                ->where('admin_estado', self::PENDIENTE)
                ->count(),
        ];
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

        $resumen = self::resumenPedido($cierre, $items);
        $revision = self::metadataRevisionCierre($cierre, $items, $resumen);

        return [
            'admin_resumen' => $resumen,
            'admin_resumen_label' => self::labelResumen($resumen),
            ...self::conteoExhibicionesAdmin($items),
            'admin_revisado_por' => $revision['admin_revisado_por'],
            'admin_revisado_at' => $revision['admin_revisado_at'],
            'admin_pedido_error_comentario' => $cierre->admin_pedido_error_comentario,
            'admin_pedido_error_reportado_at' => $cierre->admin_pedido_error_reportado_at?->toIso8601String(),
            'admin_pedido_error_reportado_por' => $cierre->adminPedidoErrorReportadoPor?->only(['id', 'name']),
            'admin_pedido_error_evidencia_url' => $cierre->admin_pedido_error_evidencia_ruta
                ? '/storage/'.$cierre->admin_pedido_error_evidencia_ruta
                : null,
            'admin_pedido_error_evidencia_nombre' => $cierre->admin_pedido_error_evidencia_nombre,
        ];
    }

    /**
     * @param  Collection<int, PedidoBmaCierrePagoItem>  $items
     * @return array{admin_revisado_por: ?array{id: int, name: string}, admin_revisado_at: ?string}
     */
    public static function metadataRevisionCierre(PedidoBmaCierrePago $cierre, Collection $items, string $resumen): array
    {
        if ($cierre->admin_pedido_error_reportado_at !== null) {
            return [
                'admin_revisado_por' => $cierre->adminPedidoErrorReportadoPor?->only(['id', 'name']),
                'admin_revisado_at' => $cierre->admin_pedido_error_reportado_at?->toIso8601String(),
            ];
        }

        if ($resumen === self::CON_ERROR) {
            $item = $items
                ->filter(
                    fn (PedidoBmaCierrePagoItem $item) => $item->admin_estado === self::CON_ERROR
                        && $item->admin_error_reportado_at !== null,
                )
                ->sortByDesc('admin_error_reportado_at')
                ->first();

            if ($item !== null) {
                return [
                    'admin_revisado_por' => $item->adminErrorReportadoPor?->only(['id', 'name']),
                    'admin_revisado_at' => $item->admin_error_reportado_at?->toIso8601String(),
                ];
            }
        }

        if ($resumen === self::CONFIRMADO) {
            $item = $items
                ->filter(
                    fn (PedidoBmaCierrePagoItem $item) => $item->admin_estado === self::CONFIRMADO
                        && $item->admin_confirmado_at !== null,
                )
                ->sortByDesc('admin_confirmado_at')
                ->first();

            if ($item !== null) {
                return [
                    'admin_revisado_por' => $item->adminConfirmadoPor?->only(['id', 'name']),
                    'admin_revisado_at' => $item->admin_confirmado_at?->toIso8601String(),
                ];
            }
        }

        return [
            'admin_revisado_por' => null,
            'admin_revisado_at' => null,
        ];
    }

    public static function labelResumen(string $resumen): string
    {
        return match ($resumen) {
            self::CONFIRMADO => 'Aprobado',
            self::CON_ERROR => 'Con error',
            'parcial' => 'Revisión parcial',
            self::PENDIENTE => 'Pendiente',
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
