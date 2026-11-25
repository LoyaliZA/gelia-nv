<?php

namespace App\Listeners;

use App\Events\PedidoBmaActualizado;
use App\Jobs\ComisionesEmpaque\ProcesarCambiosComisionesEmpaque;
use Illuminate\Support\Facades\DB;
use Throwable;

class EncolarComisionesEmpaqueAlActualizarPedido
{
    public function handle(PedidoBmaActualizado $evento): void
    {
        if (in_array(config('queue.default'), ['sync', 'null'], true)) {
            return;
        }

        try {
            if (DB::table('comisiones_empaque_cambios_fuente')->where('tipo', 'pedido')->where('fuente_id', $evento->pedidoId)->exists()) {
                ProcesarCambiosComisionesEmpaque::dispatch();
            }
        } catch (Throwable $error) {
            report($error);
        }
    }
}
