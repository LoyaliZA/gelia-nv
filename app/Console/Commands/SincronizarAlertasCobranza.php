<?php

namespace App\Console\Commands;

use App\Services\Cobranza\SincronizarAlertasOperativasService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cobranza:sincronizar-alertas')]
#[Description('Sincroniza alertas operativas de vencimiento y límite de crédito')]
class SincronizarAlertasCobranza extends Command
{
    public function __construct(
        private SincronizarAlertasOperativasService $sincronizarAlertas,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $resultado = $this->sincronizarAlertas->ejecutar();

        $vencimiento = $resultado['vencimiento'];
        $limite = $resultado['limite'];

        $this->info(sprintf(
            'Sincronización completada. Vencimiento — resueltas: %d, creadas: %d, actualizadas: %d. Límite — resueltas: %d, creadas: %d, actualizadas: %d.',
            $vencimiento['resueltas'],
            $vencimiento['creadas'],
            $vencimiento['actualizadas'],
            $limite['resueltas'],
            $limite['creadas'],
            $limite['actualizadas'],
        ));

        return self::SUCCESS;
    }
}
