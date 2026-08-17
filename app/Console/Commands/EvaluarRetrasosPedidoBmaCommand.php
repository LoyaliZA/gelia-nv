<?php

namespace App\Console\Commands;

use App\Services\ControlPedidos\EvaluarRetrasosPedidoBmaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('control-pedidos:evaluar-retrasos')]
#[Description('Evalúa retrasos de empaque y recolección en pedidos BMA y dispara alertas')]
class EvaluarRetrasosPedidoBmaCommand extends Command
{
    public function handle(EvaluarRetrasosPedidoBmaService $service): int
    {
        $this->info('Evaluando retrasos de Control Pedidos…');

        $resultado = $service->ejecutar();

        $this->info(sprintf(
            'Alertas: empaque=%d, recolección=%d (omitidos/no vencidos=%d)',
            $resultado['empaque'],
            $resultado['recoleccion'],
            $resultado['omitidos']
        ));

        return self::SUCCESS;
    }
}
