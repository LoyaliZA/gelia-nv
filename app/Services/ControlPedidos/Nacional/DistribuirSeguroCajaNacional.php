<?php

namespace App\Services\ControlPedidos\Nacional;

use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCajaRemision;
use App\Services\ControlPedidos\CalcularSeguroPedidoService;
use App\Support\ControlPedidos\Nacional\BarreraSeguroCajaNacional;

/** El cargo fijo de DHL se calcula por caja. No se parte ni se multiplica un seguro global. */
class DistribuirSeguroCajaNacional
{
    public function __construct(
        private CalcularSeguroPedidoService $seguro,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<float>
     */
    public function porLinea(PedidoBma $pedido, CatalogoPaqueteriaPedido $paqueteria, array $lineas, bool $aplica): array
    {
        if (! $aplica) {
            return array_map(fn () => 0.0, $lineas);
        }

        $pedido->loadMissing('cajas');
        $activas = $pedido->cajas->filter(fn (PedidoBmaCaja $caja) => $caja->estaActiva())->values();
        if ($activas->count() <= 1) {
            $flete = 0.0;
            foreach ($lineas as $linea) {
                $flete += round((float) ($linea['costo_envio'] ?? 0), 2);
            }
            $monto = $this->seguro->calcularCosto($paqueteria->nombre, round($flete, 2), (float) ($pedido->total_mercancia ?? 0));

            return array_map(fn ($indice) => $indice === 0 ? $monto : 0.0, array_keys($lineas));
        }

        BarreraSeguroCajaNacional::assert($pedido->fresh(['cajas']));
        $filas = PedidoBmaCajaRemision::query()
            ->whereIn('pedido_bma_caja_id', $activas->pluck('id'))
            ->where('activa', true)
            ->get()
            ->groupBy('pedido_bma_caja_id');
        $fletePorUuid = [];
        foreach ($lineas as $linea) {
            $fletePorUuid[(string) ($linea['uuid_operativo'] ?? '')] = round((float) ($linea['costo_envio'] ?? 0), 2);
        }
        $seguroPorUuid = [];
        foreach ($activas as $caja) {
            $valor = round((float) $filas->get($caja->id, collect())->sum('valor_asegurado'), 2);
            $flete = $fletePorUuid[(string) $caja->uuid_operativo] ?? round((float) ($caja->costo_envio ?? 0), 2);
            $seguroPorUuid[(string) $caja->uuid_operativo] = $this->seguro->calcularCosto($paqueteria->nombre, $flete, $valor);
        }

        return array_map(
            fn (array $linea) => $seguroPorUuid[(string) ($linea['uuid_operativo'] ?? '')] ?? 0.0,
            $lineas
        );
    }
}
