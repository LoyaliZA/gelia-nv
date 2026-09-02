<?php

namespace App\Console\Commands\PuntoVenta;

use App\Services\PuntoVenta\Resguardos\EvaluarVencimientosResguardoPdvService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pdv:evaluar-vencimientos-resguardos')]
#[Description('Evalúa vencimientos y rezagos de resguardos PDV y emite eventos idempotentes')]
class EvaluarVencimientosResguardoPdvCommand extends Command
{
    public function handle(EvaluarVencimientosResguardoPdvService $service): int
    {
        $this->info('Evaluando vencimientos y rezagos de resguardos PDV…');

        $resultado = $service->ejecutar();

        $this->info(sprintf(
            'Eventos: vencidos=%d, rezagados=%d, próximos=%d (omitidos/no umbral=%d)',
            $resultado['vencidos'],
            $resultado['rezagados'],
            $resultado['proximos'],
            $resultado['omitidos']
        ));

        return self::SUCCESS;
    }
}
