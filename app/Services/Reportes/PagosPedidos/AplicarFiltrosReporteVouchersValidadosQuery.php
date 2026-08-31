<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use App\Support\Reportes\AdminEstadoReportePagosPedidos;
use App\Support\Reportes\AlcanceExhibicionesReportePagosPedidos;
use App\Support\Reportes\ClasificacionIngresoBancario;
use App\Support\Reportes\DetectarPosiblesDuplicadosVouchersService;
use App\Support\Reportes\FechasPagoReporte;
use Illuminate\Database\Eloquent\Builder;

class AplicarFiltrosReporteVouchersValidadosQuery
{
    /** @return list<string> */
    public function formasIngresoBancario(): array
    {
        return array_values(array_filter(
            PedidoBmaPago::FORMAS_PAGO,
            fn (string $f) => ClasificacionIngresoBancario::clasificacionForma($f) === ClasificacionIngresoBancario::INGRESO_BANCARIO
        ));
    }

    /**
     * Conjunto visible en UI (puede ampliarse con filtros de estado).
     *
     * @param  array<string, mixed>  $params
     * @return Builder<PedidoBmaCierrePagoItem>
     */
    public function queryVisible(User $usuario, array $params): Builder
    {
        $query = $this->base($usuario, $params);

        if (! $this->debeAmpliarAlcance($params)) {
            $this->aplicarAlcanceBancarioDefault($query);
        }

        return $query;
    }

    /**
     * Subconjunto ingreso bancario validado (total principal del reporte).
     *
     * @param  array<string, mixed>  $params
     * @return Builder<PedidoBmaCierrePagoItem>
     */
    public function queryIngresoBancario(User $usuario, array $params): Builder
    {
        $query = $this->base($usuario, $params);
        $this->aplicarAlcanceBancarioDefault($query);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<PedidoBmaCierrePagoItem>
     */
    public function itemsVisibles(User $usuario, array $params): array
    {
        return $this->queryVisible($usuario, $params)
            ->with([
                'cierre.cliente',
                'cierre.validadoPor',
                'capturadoPor',
                'revisadoPor',
            ])
            ->orderByDesc('fecha_pago_snapshot')
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, true>
     */
    public function posiblesDuplicados(User $usuario, array $params): array
    {
        $sinFiltro = $params;
        unset($sinFiltro['posible_duplicado']);

        return app(DetectarPosiblesDuplicadosVouchersService::class)->marcar(
            $this->itemsVisibles($usuario, $sinFiltro)
        );
    }

    /** @param  array<string, mixed>  $params */
    public function debeAmpliarAlcance(array $params): bool
    {
        if (! empty($params['estados_exhibicion']) || ! empty($params['estado_exhibicion'])) {
            return true;
        }

        if (! empty($params['reportado_posteriormente'])
            || ! empty($params['posible_duplicado'])
            || ! empty($params['con_saf_relacionado'])
            || ! empty($params['con_observaciones'])) {
            return true;
        }

        if (isset($params['con_evidencia']) && $params['con_evidencia'] === '0') {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return Builder<PedidoBmaCierrePagoItem>
     */
    private function base(User $usuario, array $params): Builder
    {
        $query = PedidoBmaCierrePagoItem::query()
            ->whereHas('cierre', function (Builder $q) {
                $q->where('estado', PedidoBmaCierrePago::ESTADO_VIGENTE);
            });

        $idsVendedores = VisibilidadPedidoBma::idsVendedoresVisibles($usuario);
        if ($idsVendedores !== null) {
            $query->whereHas('cierre', fn (Builder $q) => $q->whereIn('vendedor_id', $idsVendedores));
        }

        $this->aplicarFechas($query, $params);
        AlcanceExhibicionesReportePagosPedidos::aplicarEnQuery($query, $params);
        AdminEstadoReportePagosPedidos::aplicarFiltroItem($query, $params['estado_admin'] ?? null);
        $this->aplicarFiltrosVouchers($query, $params);

        return $query;
    }

    /** @param  Builder<PedidoBmaCierrePagoItem>  $query */
    private function aplicarAlcanceBancarioDefault(Builder $query): void
    {
        $query->where('activo_para_cobertura_snapshot', true)
            ->whereIn('estado_revision_snapshot', ClasificacionIngresoBancario::estadosRevisionValidados())
            ->whereIn('forma_pago_snapshot', $this->formasIngresoBancario());
    }

    /**
     * @param  Builder<PedidoBmaCierrePagoItem>  $query
     * @param  array<string, mixed>  $params
     */
    private function aplicarFechas(Builder $query, array $params): void
    {
        if (! empty($params['fecha_pago_desde'])) {
            $query->whereDate('fecha_pago_snapshot', '>=', $params['fecha_pago_desde']);
        }
        if (! empty($params['fecha_pago_hasta'])) {
            $query->whereDate('fecha_pago_snapshot', '<=', $params['fecha_pago_hasta']);
        }
        if (! empty($params['fecha_reportada_desde'])) {
            $query->whereDate('capturado_at_snapshot', '>=', $params['fecha_reportada_desde']);
        }
        if (! empty($params['fecha_reportada_hasta'])) {
            $query->whereDate('capturado_at_snapshot', '<=', $params['fecha_reportada_hasta']);
        }
        if (! empty($params['fecha_validacion_desde']) || ! empty($params['fecha_validacion_hasta'])) {
            $query->whereHas('cierre', function (Builder $q) use ($params) {
                if (! empty($params['fecha_validacion_desde'])) {
                    $q->whereDate('validado_at', '>=', $params['fecha_validacion_desde']);
                }
                if (! empty($params['fecha_validacion_hasta'])) {
                    $q->whereDate('validado_at', '<=', $params['fecha_validacion_hasta']);
                }
            });
        }

        if (! empty($params['fecha_incompleta'])) {
            match ($params['fecha_incompleta']) {
                FechasPagoReporte::TIPO_PAGO => $query->whereNull('fecha_pago_snapshot'),
                FechasPagoReporte::TIPO_REPORTADA => $query->whereNull('capturado_at_snapshot'),
                FechasPagoReporte::TIPO_VALIDACION => $query->whereHas('cierre', fn (Builder $q) => $q->whereNull('validado_at')),
                default => null,
            };
        }
    }

    /**
     * @param  Builder<PedidoBmaCierrePagoItem>  $query
     * @param  array<string, mixed>  $params
     */
    private function aplicarFiltrosVouchers(Builder $query, array $params): void
    {
        if (! empty($params['folio_pedido'])) {
            $like = '%'.trim((string) $params['folio_pedido']).'%';
            $query->whereHas('cierre', fn (Builder $q) => $q->where('folio_snapshot', 'like', $like));
        }

        if (! empty($params['folio_remision'])) {
            $like = '%'.trim((string) $params['folio_remision']).'%';
            $query->whereHas('cierre', fn (Builder $q) => $q->where('folio_remision_snapshot', 'like', $like));
        }

        if (! empty($params['capturado_por_id'])) {
            $query->where('capturado_por_id', (int) $params['capturado_por_id']);
        }

        if (! empty($params['validado_por_id'])) {
            $id = (int) $params['validado_por_id'];
            $query->where(function (Builder $q) use ($id) {
                $q->where('revisado_por_id', $id)
                    ->orWhereHas('cierre', fn (Builder $c) => $c->where('validado_por_id', $id));
            });
        }

        if (! empty($params['monto_desde'])) {
            $query->where('monto_snapshot', '>=', (float) $params['monto_desde']);
        }
        if (! empty($params['monto_hasta'])) {
            $query->where('monto_snapshot', '<=', (float) $params['monto_hasta']);
        }

        if (! empty($params['con_saf_relacionado'])) {
            $query->whereHas('cierre', fn (Builder $q) => $q->where('saf_aplicado', '>', 0));
        }

        if (! empty($params['con_observaciones'])) {
            $query->where(function (Builder $q) {
                $q->where('estado_revision_snapshot', PedidoBmaPago::REVISION_CON_OBSERVACIONES)
                    ->orWhereNotNull('motivo_rechazo_snapshot')
                    ->where('motivo_rechazo_snapshot', '!=', '');
            });
        }

        if (! empty($params['busqueda'])) {
            $term = trim((string) $params['busqueda']);
            $like = '%'.$term.'%';
            $monto = $this->parseMontoExacto($term);

            $query->where(function (Builder $q) use ($like, $monto) {
                $q->where('referencia_snapshot', 'like', $like)
                    ->orWhere('banco_snapshot', 'like', $like)
                    ->orWhereHas('cierre', function (Builder $c) use ($like) {
                        $c->where('folio_snapshot', 'like', $like)
                            ->orWhere('folio_remision_snapshot', 'like', $like)
                            ->orWhereHas('cliente', fn (Builder $cl) => $cl->where('nombre', 'like', $like)
                                ->orWhere('numero_cliente', 'like', $like));
                    });
                if ($monto !== null) {
                    $q->orWhere('monto_snapshot', $monto);
                }
            });
        }

        if (! empty($params['reportado_posteriormente'])) {
            $query->whereNotNull('fecha_pago_snapshot')
                ->whereNotNull('capturado_at_snapshot')
                ->whereRaw('DATE(capturado_at_snapshot) > DATE(fecha_pago_snapshot)');
        }
    }

    private function parseMontoExacto(string $term): ?string
    {
        $limpio = preg_replace('/[^\d.,]/', '', $term);
        if ($limpio === null || $limpio === '') {
            return null;
        }
        $normalizado = str_replace(',', '', $limpio);
        if (! is_numeric($normalizado)) {
            return null;
        }

        return number_format((float) $normalizado, 2, '.', '');
    }
}
