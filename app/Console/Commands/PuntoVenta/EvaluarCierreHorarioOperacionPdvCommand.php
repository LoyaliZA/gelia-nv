<?php

namespace App\Console\Commands\PuntoVenta;

use App\Services\PuntoVenta\Operacion\EvaluarCierreHorarioOperacionPdvService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pdv:evaluar-cierre-horario-operacion')]
#[Description('Evalúa el horario de cierre configurable por sucursal y deja de aceptar altas nuevas')]
class EvaluarCierreHorarioOperacionPdvCommand extends Command
{
    public function handle(EvaluarCierreHorarioOperacionPdvService $service): int
    {
        $this->info('Evaluando cierre por horario de operación PDV…');

        $resultado = $service->ejecutar();

        $this->info(sprintf(
            'Sucursales evaluadas=%d, jobs encolados=%d, omitidas sin config=%d',
            $resultado['evaluadas'],
            $resultado['encoladas'],
            $resultado['omitidas'],
        ));

        return self::SUCCESS;
    }
}
