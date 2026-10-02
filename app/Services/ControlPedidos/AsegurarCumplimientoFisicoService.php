<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;

class AsegurarCumplimientoFisicoService
{
    public function ejecutar(PedidoBmaTareaPreparacion $tarea): PedidoBmaCumplimientoFisico
    {
        return PedidoBmaCumplimientoFisico::query()->firstOrCreate(
            ['pedido_bma_tarea_preparacion_id' => $tarea->id],
            [
                'estado' => PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR,
                'cantidad' => 0,
                'version' => 1,
            ]
        );
    }
}
