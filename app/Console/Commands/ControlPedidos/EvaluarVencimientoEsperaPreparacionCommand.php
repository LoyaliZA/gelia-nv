<?php

namespace App\Console\Commands\ControlPedidos;

use App\Services\ControlPedidos\CancelacionOperativaConfig;
use App\Services\ControlPedidos\SolicitarLiberacionPorVencimientoEsperaService;
use Illuminate\Console\Command;

class EvaluarVencimientoEsperaPreparacionCommand extends Command
{
    protected $signature = 'control-pedidos:evaluar-vencimiento-espera-preparacion';

    protected $description = 'Evalúa vencimiento de espera de pago y crea solicitudes de liberación idempotentes (Fase 7).';

    public function handle(
        CancelacionOperativaConfig $config,
        SolicitarLiberacionPorVencimientoEsperaService $service,
    ): int {
        if (! $config->activo()) {
            $this->info('Cancelación operativa desactivada; omitiendo evaluador.');

            return self::SUCCESS;
        }

        $result = $service->ejecutar();
        $this->info("Liberaciones por vencimiento: {$result['procesadas']}. Alertas: {$result['alertas']}.");

        return self::SUCCESS;
    }
}
