<?php

namespace App\Observers;

use App\Events\PedidoBmaActualizado;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use Illuminate\Database\Eloquent\Model;

class PreparacionPedidosRealtimeObserver
{
    public function created(Model $modelo): void
    {
        $this->emitir($modelo, 'creado');
    }

    public function updated(Model $modelo): void
    {
        $this->emitir($modelo, 'actualizado');
    }

    public function deleted(Model $modelo): void
    {
        $this->emitir($modelo, 'eliminado');
    }

    private function emitir(Model $modelo, string $accion): void
    {
        $pedidoId = $modelo instanceof PedidoBma ? $modelo->id : $modelo->pedido_bma_id;
        $tareaId = $modelo instanceof PedidoBmaTareaPreparacion ? $modelo->id : null;
        if ($modelo instanceof PedidoBmaCumplimientoFisico || $modelo instanceof PedidoBmaTareaDocumento) {
            $tareaId = $modelo->pedido_bma_tarea_preparacion_id;
            $pedidoId = $modelo->tarea?->pedido_bma_id;
        }
        if (! $pedidoId) {
            return;
        }
        PedidoBmaActualizado::dispatch((int) $pedidoId, $accion, $tareaId ? (int) $tareaId : null);
    }
}
