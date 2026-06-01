<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;

class SincronizarBonosColaboradorService
{
    public function ejecutar(RhColaborador $colaborador, array $bonos): void
    {
        $sync = [];
        foreach ($bonos as $bonoData) {
            $monto = (float) ($bonoData['monto'] ?? 0);
            if ($monto <= 0) {
                continue;
            }
            $sync[(int) $bonoData['catalogo_bono_id']] = ['monto' => $monto];
        }

        $colaborador->bonos()->sync($sync);
    }
}
