<?php

namespace App\Services\Traspasos;

use App\Models\Almacen;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;

class SincronizarAlmacenesOrigenTraspasoSucursalService
{
    /**
     * @param  list<int>  $almacenIds
     */
    public function ejecutar(Sucursal $sucursal, array $almacenIds): void
    {
        $ids = collect($almacenIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids !== []) {
            $validos = Almacen::query()
                ->whereIn('id', $ids)
                ->where('activo', true)
                ->where('visible_en_traspasos', true)
                ->pluck('id')
                ->all();

            if (count($validos) !== count($ids)) {
                throw new \InvalidArgumentException('Algunos almacenes no están activos o visibles en traspasos.');
            }
        }

        DB::transaction(function () use ($sucursal, $ids): void {
            $sucursal->almacenesOrigenTraspaso()->sync($ids);
        });
    }
}
