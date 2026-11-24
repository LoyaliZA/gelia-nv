<?php

namespace App\Services\ControlPedidos\Nacional;

use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoZonaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\CalcularSeguroPedidoService;
use App\Services\ControlPedidos\EnviosPedidoBmaConfig;

/** Desglose de la cotización antes de guardar. Usa el mismo cálculo de seguro que el guardado y no escribe. */
class VistaPreviaCotizacionNacional
{
    public function __construct(
        private DistribuirSeguroCajaNacional $seguro,
        private CalcularSeguroPedidoService $cobertura,
        private EnviosPedidoBmaConfig $config,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array{venta: float, flete: float, reexpedicion: float, guia: float, seguro: float, total: float, cobertura_seguro: bool}
     */
    public function calcular(PedidoBma $pedido, CatalogoPaqueteriaPedido $paqueteria, array $lineas, bool $aplicaSeguro, ?int $zonaId): array
    {
        $venta = round((float) ($pedido->total_mercancia ?? 0), 2);
        $flete = round(array_sum(array_map(fn (array $linea) => (float) ($linea['costo_envio'] ?? 0), $lineas)), 2);

        $reexpedicion = 0.0;
        if ($zonaId && $this->config->reexpedicionAlcance() === EnviosPedidoBmaConfig::ALCANCE_PEDIDO) {
            $reexpedicion = round((float) CatalogoZonaPedido::query()->find($zonaId)?->costoReexpedicion(), 2);
        }

        $conCobertura = $this->cobertura->tieneCobertura($paqueteria->nombre);
        $seguro = $conCobertura && $aplicaSeguro && $flete > 0
            ? round(array_sum($this->seguro->porLinea($pedido, $paqueteria, $lineas, true)), 2)
            : 0.0;
        $guia = round($flete + $reexpedicion, 2);

        return [
            'venta' => $venta,
            'flete' => $flete,
            'reexpedicion' => $reexpedicion,
            'guia' => $guia,
            'seguro' => $seguro,
            'total' => round($venta + $guia + $seguro, 2),
            'cobertura_seguro' => $conCobertura,
        ];
    }
}
