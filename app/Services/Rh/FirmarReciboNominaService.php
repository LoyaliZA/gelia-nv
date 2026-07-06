<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhReciboNomina;
use App\Models\User;
use Carbon\Carbon;

class FirmarReciboNominaService
{
    public function __construct(
        private GuardarFirmaReciboNominaService $guardarFirma,
    ) {}

    public function ejecutar(
        User $usuario,
        RhColaborador $colaborador,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        string $firmaColaboradorData,
    ): RhReciboNomina {
        $path = $this->guardarFirma->ejecutar($colaborador, $fechaInicio, $fechaFin, $firmaColaboradorData);

        return RhReciboNomina::updateOrCreate(
            [
                'rh_colaborador_id' => $colaborador->id,
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin->toDateString(),
            ],
            [
                'firma_colaborador_path' => $path,
                'firmado_por_id' => $usuario->id,
                'firmado_en' => now(),
            ],
        );
    }
}
