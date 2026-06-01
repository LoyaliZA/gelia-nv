<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use Illuminate\Support\Facades\DB;

class RecalcularSalariosRhService
{
    public function __construct(
        private CalcularSalariosColaboradorService $calcularSalarios,
    ) {}

    public function ejecutar(?RhConfiguracion $config = null): int
    {
        $config = $config ?? RhConfiguracion::obtener();
        $actualizados = 0;

        RhColaborador::query()
            ->where('activo', true)
            ->orderBy('id')
            ->chunkById(100, function ($colaboradores) use ($config, &$actualizados) {
                DB::transaction(function () use ($colaboradores, $config, &$actualizados) {
                    foreach ($colaboradores as $colaborador) {
                        $this->calcularSalarios->ejecutar($colaborador, $config);
                        $colaborador->save();
                        $actualizados++;
                    }
                });
            });

        return $actualizados;
    }
}
