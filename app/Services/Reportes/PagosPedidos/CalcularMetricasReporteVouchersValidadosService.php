<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Support\Reportes\ClasificacionIngresoBancario;
use App\Support\Reportes\FechasPagoReporte;

class CalcularMetricasReporteVouchersValidadosService
{
    public function __construct(
        private AplicarFiltrosReporteVouchersValidadosQuery $filtros,
        private CalcularIngresoBancarioValidadoService $ingresoBancario,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function ejecutar(User $usuario, array $params): array
    {
        $visibles = $this->filtros->itemsVisibles($usuario, $params);
        $duplicados = $this->filtros->posiblesDuplicados($usuario, $params);

        if (! empty($params['posible_duplicado'])) {
            $visibles = array_values(array_filter(
                $visibles,
                fn (PedidoBmaCierrePagoItem $i) => isset($duplicados[$i->id])
            ));
        }

        $bancarios = $this->filtros->queryIngresoBancario($usuario, $params)
            ->with(['cierre'])
            ->get();

        $ingreso = $this->ingresoBancario->desdeItems($bancarios);

        $porForma = [];
        foreach ($bancarios as $item) {
            $forma = (string) ($item->forma_pago_snapshot ?? 'otro');
            if (! isset($porForma[$forma])) {
                $porForma[$forma] = ['forma' => $forma, 'label' => PedidoBmaPago::labelForma($forma) ?? $forma, 'centavos' => 0, 'vouchers' => 0];
            }
            $porForma[$forma]['centavos'] += (int) round((float) $item->monto_snapshot * 100);
            $porForma[$forma]['vouchers']++;
        }

        $reportadosPosterior = 0;
        $conObservaciones = 0;
        $pendienteCentavos = 0;
        $rechazadoCentavos = 0;
        $remisionesSaf = 0;
        $totalSafCentavos = 0;
        $cierresSaf = [];

        foreach ($visibles as $item) {
            $cierre = $item->cierre;
            if ($cierre && FechasPagoReporte::reportadoPosteriormente($item->fecha_pago_snapshot, $item->capturado_at_snapshot)) {
                $reportadosPosterior++;
            }
            if ($item->estado_revision_snapshot === PedidoBmaPago::REVISION_CON_OBSERVACIONES
                || ! empty($item->motivo_rechazo_snapshot)) {
                $conObservaciones++;
            }

            $clas = ClasificacionIngresoBancario::clasificarItem($item);
            $centavos = (int) round((float) $item->monto_snapshot * 100);
            if ($clas === ClasificacionIngresoBancario::PAGO_PENDIENTE) {
                $pendienteCentavos += $centavos;
            }
            if ($clas === ClasificacionIngresoBancario::PAGO_RECHAZADO) {
                $rechazadoCentavos += $centavos;
            }

            if ($cierre && (float) $cierre->saf_aplicado > 0.005) {
                $cierresSaf[$cierre->id] = (float) $cierre->saf_aplicado;
            }
        }

        $remisionesSaf = count($cierresSaf);
        $totalSafCentavos = (int) round(array_sum($cierresSaf) * 100);

        $pedidosRelacionados = $bancarios
            ->map(fn (PedidoBmaCierrePagoItem $i) => $i->cierre?->pedido_bma_id)
            ->filter()
            ->unique()
            ->count();

        return [
            'total_ingreso_bancario' => $ingreso['total_ingreso_bancario'],
            'vouchers_validados' => $ingreso['vouchers_ingreso_bancario'],
            'pedidos_relacionados' => $pedidosRelacionados,
            'bancos_involucrados' => count($ingreso['por_banco']),
            'por_banco' => $ingreso['por_banco'],
            'por_forma_pago' => collect($porForma)
                ->sortByDesc('centavos')
                ->values()
                ->map(fn (array $f) => [
                    'forma' => $f['forma'],
                    'label' => $f['label'],
                    'total' => number_format($f['centavos'] / 100, 2, '.', ''),
                    'vouchers' => $f['vouchers'],
                ])
                ->all(),
            'reportados_posteriormente' => $reportadosPosterior,
            'con_observaciones' => $conObservaciones,
            'posibles_duplicados' => count($duplicados),
            'remisiones_con_saf' => $remisionesSaf,
            'total_saf_relacionado' => number_format($totalSafCentavos / 100, 2, '.', ''),
            'pendientes_visibles' => number_format($pendienteCentavos / 100, 2, '.', ''),
            'rechazados_visibles' => number_format($rechazadoCentavos / 100, 2, '.', ''),
            'exhibiciones_visibles' => count($visibles),
        ];
    }
}
