<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EvaluarRetrasosPedidoBmaService
{
    public function __construct(
        private PlazosRetrasoPedidoBmaConfig $configService,
        private CalcularPlazosRetrasoPedidoBmaService $plazosService,
        private NotificarPedidoBmaService $notificarService,
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    /**
     * @return array{empaque: int, recoleccion: int, omitidos: int}
     */
    public function ejecutar(?Carbon $ahora = null): array
    {
        $config = $this->configService->obtener();
        $ahora = ($ahora ?? now())->copy()->timezone(config('app.timezone'));

        if (! $config['activo']) {
            return ['empaque' => 0, 'recoleccion' => 0, 'omitidos' => 0];
        }

        $empaque = 0;
        $recoleccion = 0;
        $omitidos = 0;

        PedidoBma::query()
            ->with(['estatus', 'paqueteria', 'vendedor'])
            ->whereNotNull('pago_validado_at')
            ->whereNull('retraso_empaque_alertado_at')
            ->whereNull('empacado_at')
            ->whereNull('cancelado_at')
            ->whereNull('resguardo_apartado_at')
            ->whereHas('estatus', fn ($q) => $q->whereIn('fase_ciclo', [
                CatalogoEstatusPedido::FASE_EN_CEDIS,
                CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
            ]))
            ->orderBy('id')
            ->chunkById(100, function ($pedidos) use ($config, $ahora, &$empaque, &$omitidos) {
                foreach ($pedidos as $pedido) {
                    if ($this->evaluarEmpaque($pedido, $config, $ahora)) {
                        $empaque++;
                    } else {
                        $omitidos++;
                    }
                }
            });

        PedidoBma::query()
            ->with(['estatus', 'paqueteria', 'vendedor'])
            ->whereNotNull('empacado_at')
            ->whereNull('retraso_recoleccion_alertado_at')
            ->whereNull('cancelado_at')
            ->whereNull('resguardo_apartado_at')
            ->whereHas('estatus', fn ($q) => $q->where('fase_ciclo', CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO))
            ->orderBy('id')
            ->chunkById(100, function ($pedidos) use ($config, $ahora, &$recoleccion, &$omitidos) {
                foreach ($pedidos as $pedido) {
                    if ($this->evaluarRecoleccion($pedido, $config, $ahora)) {
                        $recoleccion++;
                    } else {
                        $omitidos++;
                    }
                }
            });

        return [
            'empaque' => $empaque,
            'recoleccion' => $recoleccion,
            'omitidos' => $omitidos,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function evaluarEmpaque(PedidoBma $pedido, array $config, Carbon $ahora): bool
    {
        $plazos = $this->configService->plazosParaCategoria(
            $config,
            $pedido->paqueteria?->categoria
        );

        $deadline = $this->plazosService->deadlineDesdeAncla(
            Carbon::parse($pedido->pago_validado_at)->timezone(config('app.timezone')),
            $plazos['dias_empaque'],
            $config
        );

        if ($ahora->lt($deadline)) {
            return false;
        }

        return $this->disparar(
            $pedido,
            'pedido_retraso_empaque',
            AccionesHistorialPedidoBma::RETRASO_EMPAQUE,
            'Retraso de empaque: el pedido no fue empacado dentro del plazo esperado.',
            'retraso_empaque_alertado_at',
            '/control-pedidos/cedis?tab=EMPACADOS&q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function evaluarRecoleccion(PedidoBma $pedido, array $config, Carbon $ahora): bool
    {
        $ancla = $this->anclaRecoleccion($pedido);
        if ($ancla === null) {
            return false;
        }

        $plazos = $this->configService->plazosParaCategoria(
            $config,
            $pedido->paqueteria?->categoria
        );

        $deadline = $this->plazosService->deadlineDesdeAncla(
            $ancla,
            $plazos['dias_recoleccion'],
            $config
        );

        if ($ahora->lt($deadline)) {
            return false;
        }

        return $this->disparar(
            $pedido,
            'pedido_retraso_recoleccion',
            AccionesHistorialPedidoBma::RETRASO_RECOLECCION,
            $this->mensajeRetrasoRecoleccion($pedido),
            'retraso_recoleccion_alertado_at',
            '/control-pedidos/cedis?tab=PENDIENTES_ENVIO&q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))
        );
    }

    private function anclaRecoleccion(PedidoBma $pedido): ?Carbon
    {
        $esLocal = $pedido->paqueteria?->categoria === CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL;

        if (! $esLocal && $pedido->guia_subida_at) {
            return Carbon::parse($pedido->guia_subida_at)->timezone(config('app.timezone'));
        }

        if ($pedido->empacado_at) {
            return Carbon::parse($pedido->empacado_at)->timezone(config('app.timezone'));
        }

        return null;
    }

    private function mensajeRetrasoRecoleccion(PedidoBma $pedido): string
    {
        $pedido->loadMissing('cajas');
        $recolectadas = (int) $pedido->cajas_recolectadas;
        $pendientes = (int) $pedido->cajas_pendientes;

        if ($recolectadas > 0 && $pendientes > 0) {
            return sprintf(
                'Retraso de recolección: %d de %d envíos ya recolectados; aún quedan %d pendientes de marcar como enviados.',
                $recolectadas,
                $recolectadas + $pendientes,
                $pendientes
            );
        }

        return 'Retraso de recolección: el paquete estaba listo pero no fue marcado como enviado a tiempo.';
    }

    private function disparar(
        PedidoBma $pedido,
        string $tipoAlerta,
        string $accionHistorial,
        string $mensaje,
        string $columnaAlertado,
        string $url,
    ): bool {
        try {
            DB::transaction(function () use ($pedido, $tipoAlerta, $accionHistorial, $mensaje, $columnaAlertado, $url) {
                $afectadas = PedidoBma::query()
                    ->where('id', $pedido->id)
                    ->whereNull($columnaAlertado)
                    ->update([$columnaAlertado => now()]);

                if ($afectadas === 0) {
                    return;
                }

                $estatusId = (int) $pedido->catalogo_estatus_pedido_id;

                $this->historialService->ejecutar(
                    $pedido->id,
                    null,
                    $estatusId,
                    $estatusId,
                    $mensaje,
                    $accionHistorial
                );

                $this->notificarService->ejecutar(
                    $pedido,
                    $tipoAlerta,
                    $mensaje,
                    ['control_pedidos.cedis', 'control_pedidos.delegado'],
                    null,
                    true,
                    ['url' => $url],
                    true,
                );
            });

            return (bool) $pedido->fresh()?->{$columnaAlertado};
        } catch (\Throwable $e) {
            Log::error('Error al evaluar retraso de pedido BMA', [
                'pedido_id' => $pedido->id,
                'tipo' => $tipoAlerta,
                'error' => $e->getMessage(),
            ]);
            report($e);

            return false;
        }
    }
}
