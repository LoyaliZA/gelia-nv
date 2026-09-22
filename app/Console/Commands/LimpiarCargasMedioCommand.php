<?php

namespace App\Console\Commands;

use App\Services\Medios\GestionarCargaMedioService;
use Illuminate\Console\Command;

class LimpiarCargasMedioCommand extends Command
{
    protected $signature = 'medios:limpiar-cargas';

    protected $description = 'Aborta cargas multipart expiradas o abandonadas en el almacén de medios';

    public function handle(GestionarCargaMedioService $servicio): int
    {
        $n = $servicio->limpiarExpiradas();
        $this->info('Cargas marcadas como expiradas: '.$n);

        return self::SUCCESS;
    }
}
