<?php

namespace App\Support\Reportes;

use App\Models\Reportes\PedidoBmaCierrePagoItem;
use Illuminate\Database\Eloquent\Builder;

/** Filtros de exhibición compartidos entre métricas, listado y PDF. */
final class AlcanceExhibicionesReportePagosPedidos
{
    /** @param  array<string, mixed>  $params */
    public static function tieneFiltrosItem(array $params): bool
    {
        return ! empty($params['banco_id'])
            || ! empty($params['banco_ids'])
            || ! empty($params['sin_banco'])
            || ! empty($params['forma_pago'])
            || ! empty($params['formas_pago'])
            || ! empty($params['estado_exhibicion'])
            || ! empty($params['estados_exhibicion'])
            || ! empty($params['referencia_bancaria'])
            || isset($params['con_evidencia']);
    }

    /** @param  Builder<PedidoBmaCierrePagoItem>  $items
     * @param  array<string, mixed>  $params
     */
    public static function aplicarEnQuery(Builder $items, array $params): void
    {
        $bancoIds = self::bancoIds($params);
        $sinBanco = ! empty($params['sin_banco']);

        if ($bancoIds !== [] || $sinBanco) {
            $items->where(function (Builder $q) use ($bancoIds, $sinBanco) {
                if ($bancoIds !== []) {
                    $q->whereIn('catalogo_banco_id', $bancoIds);
                }
                if ($sinBanco) {
                    $q->orWhereNull('catalogo_banco_id');
                }
            });
        }

        $formas = self::formasPago($params);
        if ($formas !== []) {
            $items->whereIn('forma_pago_snapshot', $formas);
        }

        $estados = self::estadosExhibicion($params);
        if ($estados !== []) {
            $items->where(function (Builder $q) use ($estados) {
                $normales = array_values(array_filter($estados, fn ($e) => $e !== 'sustituido'));
                if ($normales !== []) {
                    $q->whereIn('estado_revision_snapshot', $normales);
                }
                if (in_array('sustituido', $estados, true)) {
                    $q->orWhere(function (Builder $s) {
                        $s->where('activo_para_cobertura_snapshot', false)
                            ->whereExists(function ($sub) {
                                $sub->selectRaw('1')
                                    ->from('pedido_bma_cierre_pago_items as reemplazo')
                                    ->whereColumn('reemplazo.pedido_bma_cierre_pago_id', 'pedido_bma_cierre_pago_items.pedido_bma_cierre_pago_id')
                                    ->whereColumn('reemplazo.reemplaza_pago_id', 'pedido_bma_cierre_pago_items.pedido_bma_pago_id');
                            });
                    });
                }
            });
        } elseif (! empty($params['estado_exhibicion']) && $params['estado_exhibicion'] === 'sustituido') {
            $items->where('activo_para_cobertura_snapshot', false)
                ->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('pedido_bma_cierre_pago_items as reemplazo')
                        ->whereColumn('reemplazo.pedido_bma_cierre_pago_id', 'pedido_bma_cierre_pago_items.pedido_bma_cierre_pago_id')
                        ->whereColumn('reemplazo.reemplaza_pago_id', 'pedido_bma_cierre_pago_items.pedido_bma_pago_id');
                });
        } elseif (! empty($params['estado_exhibicion'])) {
            $items->where('estado_revision_snapshot', $params['estado_exhibicion']);
        }

        if (! empty($params['referencia_bancaria'])) {
            $items->where('referencia_snapshot', 'like', '%'.$params['referencia_bancaria'].'%');
        }

        if (isset($params['con_evidencia'])) {
            if ($params['con_evidencia'] === '1') {
                $items->whereNotNull('ruta_archivo_snapshot')->where('ruta_archivo_snapshot', '!=', '');
            } elseif ($params['con_evidencia'] === '0') {
                $items->where(function (Builder $q) {
                    $q->whereNull('ruta_archivo_snapshot')->orWhere('ruta_archivo_snapshot', '');
                });
            }
        }
    }

    /**
     * @param  iterable<PedidoBmaCierrePagoItem>  $items
     * @param  array<string, mixed>  $params
     * @return list<PedidoBmaCierrePagoItem>
     */
    public static function filtrar(iterable $items, array $params): array
    {
        $lista = $items instanceof \Traversable && ! is_array($items)
            ? iterator_to_array($items)
            : [...$items];

        if (! self::tieneFiltrosItem($params)) {
            return array_values($lista);
        }

        return array_values(array_filter($lista, fn (PedidoBmaCierrePagoItem $item) => self::itemCoincide($item, $params)));
    }

    public static function itemTieneVoucher(PedidoBmaCierrePagoItem $item): bool
    {
        $ruta = $item->ruta_archivo_snapshot;

        return $ruta !== null && $ruta !== '';
    }

    /** @param  array<string, mixed>  $params */
    private static function itemCoincide(PedidoBmaCierrePagoItem $item, array $params): bool
    {
        $bancoIds = self::bancoIds($params);
        $sinBanco = ! empty($params['sin_banco']);
        if ($bancoIds !== [] || $sinBanco) {
            $okBanco = in_array((int) $item->catalogo_banco_id, $bancoIds, true);
            $okSin = $sinBanco && $item->catalogo_banco_id === null;
            if (! $okBanco && ! $okSin) {
                return false;
            }
        }

        $formas = self::formasPago($params);
        if ($formas !== [] && ! in_array($item->forma_pago_snapshot, $formas, true)) {
            return false;
        }

        $estados = self::estadosExhibicion($params);
        if ($estados !== []) {
            $matchNormal = in_array($item->estado_revision_snapshot, array_filter($estados, fn ($e) => $e !== 'sustituido'), true);
            $matchSust = in_array('sustituido', $estados, true)
                && ! $item->activo_para_cobertura_snapshot
                && self::itemFueSustituido($item);
            if (! $matchNormal && ! $matchSust) {
                return false;
            }
        } elseif (! empty($params['estado_exhibicion']) && $params['estado_exhibicion'] !== 'sustituido') {
            if ($item->estado_revision_snapshot !== $params['estado_exhibicion']) {
                return false;
            }
        }

        if (! empty($params['referencia_bancaria'])) {
            $ref = (string) ($item->referencia_snapshot ?? '');
            if (! str_contains(strtolower($ref), strtolower((string) $params['referencia_bancaria']))) {
                return false;
            }
        }

        if (isset($params['con_evidencia'])) {
            $tiene = self::itemTieneVoucher($item);
            if ($params['con_evidencia'] === '1' && ! $tiene) {
                return false;
            }
            if ($params['con_evidencia'] === '0' && $tiene) {
                return false;
            }
        }

        return true;
    }

    private static function itemFueSustituido(PedidoBmaCierrePagoItem $item): bool
    {
        return PedidoBmaCierrePagoItem::query()
            ->where('pedido_bma_cierre_pago_id', $item->pedido_bma_cierre_pago_id)
            ->where('reemplaza_pago_id', $item->pedido_bma_pago_id)
            ->exists();
    }

    /** @param  array<string, mixed>  $params
     * @return list<int>
     */
    private static function bancoIds(array $params): array
    {
        $ids = array_map('intval', $params['banco_ids'] ?? []);
        if (! empty($params['banco_id'])) {
            $ids[] = (int) $params['banco_id'];
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** @param  array<string, mixed>  $params
     * @return list<string>
     */
    private static function formasPago(array $params): array
    {
        $formas = $params['formas_pago'] ?? [];
        if (! empty($params['forma_pago'])) {
            $formas[] = $params['forma_pago'];
        }

        return array_values(array_unique(array_filter(array_map('strval', $formas))));
    }

    /** @param  array<string, mixed>  $params
     * @return list<string>
     */
    private static function estadosExhibicion(array $params): array
    {
        $estados = $params['estados_exhibicion'] ?? [];
        if (! empty($params['estado_exhibicion'])) {
            $estados[] = $params['estado_exhibicion'];
        }

        return array_values(array_unique(array_filter(array_map('strval', $estados))));
    }
}
