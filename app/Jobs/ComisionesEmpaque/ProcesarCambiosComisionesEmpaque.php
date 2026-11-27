<?php

namespace App\Jobs\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\ConfiguracionComisionEmpaque;
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
        Cache::put('comisiones-empaque:worker-activo', now()->timestamp, 240);
        $config = ConfiguracionComisionEmpaque::actual();
        if (! $config->fecha_inicio) {
            return;
        }

        if ($config->version_rango !== $config->version_reconciliada) {
            $resultado = $sincronizar->ejecutar(null, automatica: true, versionRango: (int) $config->version_rango);
            if ($resultado->estado === 'error') {
                throw new RuntimeException($resultado->mensaje);
            }
            if ($resultado->estado !== 'ok' || ! ConfiguracionComisionEmpaque::query()->whereKey($config->id)
                ->where('version_rango', $config->version_rango)->update(['version_reconciliada' => $config->version_rango])) {
                return;
            }
        }

        // Acotar cada ejecución. El scheduler continúa si llegan más lotes.
        for ($lote = 0; $lote < 10; $lote++) {
            $cambios = DB::table('comisiones_empaque_cambios_fuente')->orderBy('id')->limit(500)->get();
            if ($cambios->isEmpty()) {
                return;
            }

            $resultado = $sincronizar->ejecutar(null, pedidos: self::pedidos($cambios), automatica: true);
            if ($resultado->estado === 'error') {
                throw new RuntimeException($resultado->mensaje);
            }
            if ($resultado->estado !== 'ok') {
                return;
            }

            // Acreditar el lote solo si sigue vigente el rango con que se inició.
            $retirado = DB::transaction(function () use ($config, $cambios) {
                $actual = ConfiguracionComisionEmpaque::query()->lockForUpdate()->findOrFail($config->id);
                if ($actual->version_rango !== $config->version_rango) {
                    return false;
                }

                DB::table('comisiones_empaque_cambios_fuente')->whereIn('id', $cambios->pluck('id'))->delete();

                return true;
            });
            if (! $retirado) {
                return;
            }
            Cache::put('comisiones-empaque:worker-activo', now()->timestamp, 240);
        }
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
