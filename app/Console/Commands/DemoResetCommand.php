<?php

namespace App\Console\Commands;

use App\Services\Demo\SemillaModoDemo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:reset')]
#[Description('Borra la operación de demostración y vuelve a dejar la semilla mínima')]
class DemoResetCommand extends Command
{
    public function handle(SemillaModoDemo $semilla): int
    {
        $semilla->reiniciarOperacion();
        $this->info('Operación de demostración restaurada.');

        return self::SUCCESS;
    }
}
