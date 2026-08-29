<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\User;
use App\Support\Reportes\AlcanceExhibicionesReportePagosPedidos;
use Illuminate\Database\Eloquent\Builder;

class EstimarExportacionReportePagosPedidosService
{
    public function __construct(
        private CalcularMetricasReportePagosPedidosService $metricas,
        private AplicarFiltrosReportePagosPedidosQuery $filtros,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{pedidos: int, exhibiciones: int, vouchers: int, tamano_bytes: int, tamano_etiqueta: string, formato: string}
     */
    public function ejecutar(User $usuario, array $params): array
    {
        $formato = $params['formato'] ?? 'pdf';
        $metricas = $this->metricas->ejecutar($usuario, $params);

        $pedidos = (int) ($metricas['pedidos_validados'] ?? 0);
        $exhibiciones = (int) ($metricas['exhibiciones_incluidas'] ?? 0);
        $vouchers = $this->contarVouchers($usuario, $params);

        $bytes = $this->estimarTamanoBytes($formato, $params, $pedidos, $exhibiciones, $vouchers);

        return [
            'pedidos' => $pedidos,
            'exhibiciones' => $exhibiciones,
            'vouchers' => $vouchers,
            'tamano_bytes' => $bytes,
            'tamano_etiqueta' => self::formatearTamano($bytes),
            'formato' => $formato,
        ];
    }

    /** @param  array<string, mixed>  $params */
    private function contarVouchers(User $usuario, array $params): int
    {
        if (($params['incluir_vouchers'] ?? true) === false) {
            return 0;
        }

        $query = PedidoBmaCierrePago::query();
        $this->filtros->aplicar($query, $usuario, $params);

        $items = PedidoBmaCierrePagoItem::query()
            ->whereIn('pedido_bma_cierre_pago_id', (clone $query)->select('pedido_bma_cierres_pago.id'))
            ->whereNotNull('ruta_archivo_snapshot')
            ->where('ruta_archivo_snapshot', '!=', '');

        if (AlcanceExhibicionesReportePagosPedidos::tieneFiltrosItem($params)) {
            AlcanceExhibicionesReportePagosPedidos::aplicarEnQuery($items, $params);
        }

        if (($params['incluir_evidencias_rechazadas_sustituidas'] ?? true) === false) {
            $items->where('estado_revision_snapshot', '!=', 'rechazado')
                ->where(function (Builder $q) {
                    $q->where('activo_para_cobertura_snapshot', true)
                        ->orWhereNotExists(function ($sub) {
                            $sub->selectRaw('1')
                                ->from('pedido_bma_cierre_pago_items as reemplazo')
                                ->whereColumn('reemplazo.pedido_bma_cierre_pago_id', 'pedido_bma_cierre_pago_items.pedido_bma_cierre_pago_id')
                                ->whereColumn('reemplazo.reemplaza_pago_id', 'pedido_bma_cierre_pago_items.pedido_bma_pago_id');
                        });
                });
        }

        return $items->count();
    }

    /**
     * ponytail: heurística fija; calibrar con exportaciones reales si el PDF crece mucho.
     *
     * @param  array<string, mixed>  $params
     */
    private function estimarTamanoBytes(string $formato, array $params, int $pedidos, int $exhibiciones, int $vouchers): int
    {
        if ($formato === 'csv_resumen') {
            return max(512, $pedidos * 1500);
        }
        if ($formato === 'csv_detalle') {
            return max(512, $exhibiciones * 800);
        }

        $bytes = 250_000 + ($pedidos * 45_000) + ($exhibiciones * 2_000);

        if ($vouchers > 0) {
            $bytes += $vouchers * 500_000;
        }
        if (($params['incluir_referencias_remision'] ?? true) !== false) {
            $bytes += $pedidos * 500;
        }

        return max(512, $bytes);
    }

    public static function formatearTamano(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            $mb = $bytes / 1_048_576;

            return ($mb >= 10 ? (string) round($mb) : number_format($mb, 1, '.', '')).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024).' KB';
        }

        return $bytes.' B';
    }
}
