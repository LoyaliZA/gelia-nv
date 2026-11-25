<?php

namespace App\Jobs\ComisionesEmpaque;

use App\Models\ControlPedidos\OperacionEmpaqueMiembro;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCajaRemision;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Services\ComisionesEmpaque\SincronizarComisionesEmpaqueService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProcesarCambiosComisionesEmpaque implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [5, 20];

    public int $uniqueFor = 900;

    public function handle(SincronizarComisionesEmpaqueService $sincronizar): void
    {
        $cambios = DB::table('comisiones_empaque_cambios_fuente')->orderBy('id')->limit(500)->get();
        if ($cambios->isNotEmpty()) {
            $resultado = $sincronizar->ejecutar(null, pedidos: self::pedidos($cambios), automatica: true);
            if ($resultado->estado === 'error') {
                throw new RuntimeException($resultado->mensaje);
            }

            // Retirar solo mensajes de la cola de origen ya procesados. Los
            // mensajes escritos mientras se ejecutaba el trabajo siguen en cola.
            DB::table('comisiones_empaque_cambios_fuente')->whereIn('id', $cambios->pluck('id'))->delete();
        }
        Cache::put('comisiones-empaque:worker-activo', now()->timestamp, 240);
    }

    /** @return list<int> */
    public static function pedidos(Collection $cambios): array
    {
        $grupos = $cambios->groupBy('tipo')->map->pluck('fuente_id');
        $ids = ($grupos['pedido'] ?? collect())
            ->merge(PedidoBmaTareaPreparacion::query()->whereKey(($grupos['tarea'] ?? collect())->all())->pluck('pedido_bma_id'))
            ->merge(PedidoBmaCaja::query()->whereKey(($grupos['caja'] ?? collect())->all())->pluck('pedido_bma_id'))
            ->unique();

        $cajasVinculadas = PedidoBmaCajaRemision::query()->whereIn('pedido_bma_id', $ids)->where('activa', true)->select('pedido_bma_caja_id');
        $ids = $ids->merge(PedidoBmaCaja::query()->whereIn('id', $cajasVinculadas)->pluck('pedido_bma_id'))->unique();
        $operaciones = OperacionEmpaqueMiembro::query()->whereIn('pedido_bma_id', $ids)->pluck('operacion_empaque_id')
            ->merge($grupos['operacion'] ?? collect())->unique();

        return $ids->merge(OperacionEmpaqueMiembro::query()->whereIn('operacion_empaque_id', $operaciones)->pluck('pedido_bma_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}
