<?php

namespace App\Console\Commands\Comercial;

use App\Services\Comercial\VisitasProgramadas\CerrarVisitasProgramadasVencidasService;
use Illuminate\Console\Command;

class CerrarVisitasProgramadasDiaCommand extends Command
{
    protected $signature = 'visitas-programadas:cerrar-dia';

    protected $description = 'Marca como no asistió las visitas programadas vencidas sin llegada confirmada';

    public function handle(CerrarVisitasProgramadasVencidasService $cerrar): int
    {
        $afectadas = $cerrar->handle();

        $this->info("Visitas cerradas: {$afectadas}");

        return self::SUCCESS;
    }
}
