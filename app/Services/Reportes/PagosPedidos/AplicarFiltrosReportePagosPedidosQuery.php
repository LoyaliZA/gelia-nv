<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\User;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use App\Support\Reportes\AdminEstadoReportePagosPedidos;
use App\Support\Reportes\FechasPagoReporte;
use Illuminate\Database\Eloquent\Builder;

class AplicarFiltrosReportePagosPedidosQuery
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function aplicar(Builder $query, User $usuario, array $filtros): Builder
    {
        $idsVendedores = VisibilidadPedidoBma::idsVendedoresVisibles($usuario);
        if ($idsVendedores !== null) {
            $query->whereIn('vendedor_id', $idsVendedores);
        }

        $estadoCierre = $filtros['estado_cierre'] ?? 'vigente';
        if ($estadoCierre === 'vigente') {
            $query->where('estado', PedidoBmaCierrePago::ESTADO_VIGENTE);
        } elseif ($estadoCierre === 'revocado') {
            $query->where('estado', PedidoBmaCierrePago::ESTADO_REVOCADO);
        } elseif ($estadoCierre === 'reconstruido') {
            $query->where('origen', PedidoBmaCierrePago::ORIGEN_BACKFILL);
        }

        $this->aplicarRangoFechasCierre($query, $filtros);

        if (! empty($filtros['departamento_id'])) {
            $query->where('departamento_id', (int) $filtros['departamento_id']);
        }
        if (! empty($filtros['vendedor_id'])) {
            $query->where('vendedor_id', (int) $filtros['vendedor_id']);
        }
        if (! empty($filtros['cliente_id'])) {
            $query->where('cliente_id', (int) $filtros['cliente_id']);
        }
        if (! empty($filtros['almacen_id'])) {
            $query->where('almacen_id', (int) $filtros['almacen_id']);
        }

        $estadosCobertura = $filtros['estados_cobertura'] ?? [];
        if (! empty($estadosCobertura)) {
            $query->whereIn('estado_cobertura', $estadosCobertura);
        } elseif (! empty($filtros['estado_cobertura'])) {
            $query->where('estado_cobertura', $filtros['estado_cobertura']);
        }

        if ($this->tieneFiltrosExhibicionCierre($filtros)) {
            $query->whereHas('items', fn (Builder $q) => $this->aplicarFiltrosExhibicionEnItems($q, $filtros));
        }

        if (isset($filtros['con_remision'])) {
            if ($filtros['con_remision'] === '1') {
                $query->whereNotNull('folio_remision_snapshot')
                    ->where('folio_remision_snapshot', '!=', '');
            } elseif ($filtros['con_remision'] === '0') {
                $query->where(function (Builder $q) {
                    $q->whereNull('folio_remision_snapshot')->orWhere('folio_remision_snapshot', '');
                });
            }
        }
        if (isset($filtros['con_evidencia'])) {
            $query->whereHas('items', function (Builder $q) use ($filtros) {
                if ($filtros['con_evidencia'] === '1') {
                    $q->whereNotNull('ruta_archivo_snapshot')->where('ruta_archivo_snapshot', '!=', '');
                } else {
                    $q->where(function (Builder $inner) {
                        $inner->whereNull('ruta_archivo_snapshot')->orWhere('ruta_archivo_snapshot', '');
                    });
                }
            });
        }
        if (! empty($filtros['origen_pedido'])) {
            $origen = $filtros['origen_pedido'];
            $query->where(function (Builder $q) use ($origen) {
                $q->where('metadata_snapshot->origen', $origen)
                    ->orWhereHas('pedido.origen', fn (Builder $o) => $o->where('nombre', $origen));
            });
        }
        if (! empty($filtros['busqueda'])) {
            $term = trim((string) $filtros['busqueda']);
            $like = '%'.$term.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('folio_snapshot', 'like', $like)
                    ->orWhere('folio_remision_snapshot', 'like', $like)
                    ->orWhereHas('cliente', fn (Builder $c) => $c->where('nombre', 'like', $like)
                        ->orWhere('numero_cliente', 'like', $like))
                    ->orWhereHas('vendedor', fn (Builder $v) => $v->where('name', 'like', $like))
                    ->orWhereHas('items', fn (Builder $i) => $i->where('referencia_snapshot', 'like', $like));
            });
        }

        if (! empty($filtros['fecha_incompleta'])) {
            FechasPagoReporte::aplicarFiltroIncompleta($query, (string) $filtros['fecha_incompleta']);
        }

        AdminEstadoReportePagosPedidos::aplicarFiltroCierre($query, $filtros['estado_admin'] ?? null);

        return $query;
    }

    /** @param  array<string, mixed>  $filtros */
    public function aplicarFiltrosExhibicionEnItems(Builder $items, array $filtros): void
    {
        \App\Support\Reportes\AlcanceExhibicionesReportePagosPedidos::aplicarEnQuery($items, $filtros);
    }

    /** @param  array<string, mixed>  $filtros */
    private function aplicarRangoFechasCierre(Builder $query, array $filtros): void
    {
        if (! empty($filtros['fecha_validacion_desde'])) {
            $query->whereDate('validado_at', '>=', $filtros['fecha_validacion_desde']);
        }
        if (! empty($filtros['fecha_validacion_hasta'])) {
            $query->whereDate('validado_at', '<=', $filtros['fecha_validacion_hasta']);
        }
        if (! empty($filtros['fecha_pedido_desde'])) {
            $query->whereDate('pedido_fecha', '>=', $filtros['fecha_pedido_desde']);
        }
        if (! empty($filtros['fecha_pedido_hasta'])) {
            $query->whereDate('pedido_fecha', '<=', $filtros['fecha_pedido_hasta']);
        }
        if (! empty($filtros['fecha_reportada_desde']) || ! empty($filtros['fecha_reportada_hasta'])) {
            $query->whereHas('items', function (Builder $q) use ($filtros) {
                if (! empty($filtros['fecha_reportada_desde'])) {
                    $q->whereDate('capturado_at_snapshot', '>=', $filtros['fecha_reportada_desde']);
                }
                if (! empty($filtros['fecha_reportada_hasta'])) {
                    $q->whereDate('capturado_at_snapshot', '<=', $filtros['fecha_reportada_hasta']);
                }
            });
        }
        if (! empty($filtros['fecha_pago_desde']) || ! empty($filtros['fecha_pago_hasta'])) {
            $query->whereHas('items', function (Builder $q) use ($filtros) {
                if (! empty($filtros['fecha_pago_desde'])) {
                    $q->whereDate('fecha_pago_snapshot', '>=', $filtros['fecha_pago_desde']);
                }
                if (! empty($filtros['fecha_pago_hasta'])) {
                    $q->whereDate('fecha_pago_snapshot', '<=', $filtros['fecha_pago_hasta']);
                }
            });
        }
    }

    /** @param  array<string, mixed>  $filtros */
    private function tieneFiltrosExhibicionCierre(array $filtros): bool
    {
        return \App\Support\Reportes\AlcanceExhibicionesReportePagosPedidos::tieneFiltrosItem($filtros);
    }
}
