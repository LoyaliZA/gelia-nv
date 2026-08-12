<?php

namespace App\Console\Commands;

use App\Services\SaldosAFavor\VencerCreditosSafService;
use Illuminate\Console\Command;

class VencerCreditosSafCommand extends Command
{
    protected $signature = 'saldos-favor:vencer-creditos';

    protected $description = 'Marca como vencidos los créditos SAF con fecha de vencimiento vencida y remanente.';

    public function handle(VencerCreditosSafService $service): int
    {
        $n = $service->handle();
        $this->info("Créditos vencidos: {$n}");

        return self::SUCCESS;
    }
}
