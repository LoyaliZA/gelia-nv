<?php

namespace App\Console\Commands\PuntoVenta;

use App\Services\PuntoVenta\Operacion\EvaluarAperturaHorarioOperacionPdvService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pdv:evaluar-apertura-horario-operacion')]
#[Description('Evalúa el horario de apertura configurable por sucursal y habilita altas nuevas de turnos')]
class EvaluarAperturaHorarioOperacionPdvCommand extends Command
{
    public function handle(EvaluarAperturaHorarioOperacionPdvService $service): int
    {
        $this->info('Evaluando apertura por horario de operación PDV…');

        $resultado = $service->ejecutar();

        $this->info(sprintf(
            'Sucursales evaluadas=%d, jobs encolados=%d, omitidas sin apertura=%d',
            $resultado['evaluadas'],
            $resultado['encoladas'],
            $resultado['omitidas'],
        ));

        return self::SUCCESS;
    }
}
