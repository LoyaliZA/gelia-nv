<?php

namespace App\Services\Tiendanube;

use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeSyncLog;
use App\Models\Tiendanube\TiendanubeSyncRecursoVisto;

class TiendanubeSyncRecursoVistoService
{
    /**
     * @param  list<array<string, mixed>>  $recursos
     */
    public function registrarPagina(TiendanubeSyncLog $log, string $tipo, array $recursos): void
    {
        $now = now();
        $filas = [];

        foreach ($recursos as $recurso) {
            $id = (int) $recurso['id'];
            if ($id < 1) {
                continue;
            }
            $filas[] = [
                'sync_log_id' => $log->id,
                'tipo' => $tipo,
                'recurso_id' => $id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($filas === []) {
            return;
        }

        TiendanubeSyncRecursoVisto::query()->insertOrIgnore($filas);
    }

    /**
     * @return list<int>
     */
    public function candidatos(TiendanubeSyncLog $log, string $tipo): array
    {
        $vistos = TiendanubeSyncRecursoVisto::query()
            ->where('sync_log_id', $log->id)
            ->where('tipo', $tipo)
            ->pluck('recurso_id');

        $query = $tipo === TiendanubeSyncRecursoVisto::TIPO_PRODUCTO
            ? TiendanubeProducto::query()
            : TiendanubeCategoria::query();

        if ($vistos->isNotEmpty()) {
            $query->whereNotIn('id', $vistos->all());
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function limpiarPorRetencion(): int
    {
        $dias = max(1, (int) config('tiendanube.sync_vistos_retencion_dias', 14));
        $limite = now()->subDays($dias);

        $ids = TiendanubeSyncLog::query()
            ->whereIn('estado', ['completado', 'error'])
            ->where('updated_at', '<', $limite)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return TiendanubeSyncRecursoVisto::query()
            ->whereIn('sync_log_id', $ids)
            ->delete();
    }
}
