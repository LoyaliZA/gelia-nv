<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\PlataformaPago;
use Illuminate\Support\Facades\DB;

class ActualizarComisionesPlataformasService
{
    /**
     * @param  array<int, array{id: int, tasa_comision_pct: float}>  $plataformas
     */
    public function ejecutar(array $plataformas): void
    {
        DB::transaction(function () use ($plataformas) {
            foreach ($plataformas as $item) {
                PlataformaPago::query()
                    ->whereKey($item['id'])
                    ->update(['tasa_comision_pct' => $item['tasa_comision_pct']]);
            }
        });
    }
}
