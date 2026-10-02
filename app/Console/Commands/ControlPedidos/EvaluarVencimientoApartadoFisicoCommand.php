<?php

namespace App\Console\Commands\ControlPedidos;

use App\Services\ControlPedidos\EvaluarVencimientoApartadoFisicoService;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use Illuminate\Console\Command;

class EvaluarVencimientoApartadoFisicoCommand extends Command
{
    protected $signature = 'control-pedidos:evaluar-vencimiento-apartado-fisico';

    protected $description = 'Pasa a devolución pendiente las recogidas de hoy ya vencidas. No mueve inventario.';

    public function handle(PreparacionTiendaConfig $config, EvaluarVencimientoApartadoFisicoService $service): int
    {
        if (! $config->activo()) {
            $this->info('Preparación Tienda desactivada; sin evaluación de apartado.');

            return self::SUCCESS;
        }

        $movidas = $service->ejecutar();
        $this->info("Apartados pasados a devolución pendiente: {$movidas}");

        return self::SUCCESS;
    }
}
