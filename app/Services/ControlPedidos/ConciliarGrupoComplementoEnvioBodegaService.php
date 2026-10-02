<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;

/**
 * Vista de conciliación 9+1: cada línea conserva el expediente (origen) de sus unidades.
 */
class ConciliarGrupoComplementoEnvioBodegaService
{
    /**
     * @return array<string, mixed>|null
     */
    public function paraPedido(PedidoBma $pedido): ?array
    {
        $raiz = $pedido->esComplemento()
            ? ($pedido->principal ?? $pedido)
            : $pedido;

        $raiz->loadMissing([
            'complementos.tareasPreparacion.productos',
            'complementos.tareasPreparacion.almacen',
            'tareasPreparacion.productos',
            'tareasPreparacion.almacen',
        ]);

        $expedientes = collect([$raiz])->merge($raiz->complementos ?? []);
        if ($expedientes->count() < 2) {
            return null;
        }

        $lineas = [];
        foreach ($expedientes as $exp) {
            $tarea = $this->tareaTrasladoVigente($exp);
            if (! $tarea) {
                continue;
            }
            $tarea->loadMissing(['productos', 'almacen', 'solicitudTraspaso']);
            foreach ($tarea->productos as $prod) {
                $cantidad = (int) $prod->cantidad_encontrada;
                if ($cantidad <= 0 && (int) $prod->cantidad_solicitada <= 0) {
                    continue;
                }
                $origenId = (int) ($prod->pedido_bma_origen_id ?: $exp->id);
                $lineas[] = [
                    'pedido_bma_id' => $exp->id,
                    'pedido_bma_origen_id' => $origenId,
                    'folio_expediente' => $exp->folio,
                    'es_complemento' => $exp->esComplemento(),
                    'almacen' => $tarea->almacen?->nombre,
                    'sku' => $prod->sku,
                    'descripcion' => $prod->descripcion_snapshot,
                    'cantidad_solicitada' => (int) $prod->cantidad_solicitada,
                    'cantidad_encontrada' => $cantidad,
                    'tarea_id' => $tarea->id,
                    'traspaso_folio' => $tarea->solicitudTraspaso?->folio,
                ];
            }
        }

        if ($lineas === []) {
            return null;
        }

        return [
            'pedido_raiz_id' => $raiz->id,
            'folio_raiz' => $raiz->folio,
            'total_expedientes' => $expedientes->count(),
            'lineas' => $lineas,
        ];
    }

    private function tareaTrasladoVigente(PedidoBma $pedido): ?PedidoBmaTareaPreparacion
    {
        return $pedido->tareasPreparacion()
            ->where('requiere_traslado_cedis', true)
            ->orderByDesc('id')
            ->first();
    }
}
