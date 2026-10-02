<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;

class DesgloseSkuPreparacion
{
    public static function mensaje(PedidoBmaTareaPreparacion $tarea): ?string
    {
        $tarea->loadMissing('productos');

        if ($tarea->productos->isEmpty()) {
            return 'Capture el desglose de SKU y cantidad antes de responder.';
        }

        foreach ($tarea->productos as $producto) {
            $sku = trim((string) $producto->sku);
            $descripcion = (string) $producto->descripcion_snapshot;
            if ($sku === '' || str_starts_with($descripcion, 'Piezas del pedido')) {
                return 'El renglón genérico no confirma el surtido. Capture SKU y cantidad de cada pieza.';
            }
        }

        return null;
    }
}
