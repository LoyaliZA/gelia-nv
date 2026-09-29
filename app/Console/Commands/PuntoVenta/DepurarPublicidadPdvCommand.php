<?php

namespace App\Console\Commands\PuntoVenta;

use App\Services\PuntoVenta\Publicidad\DepurarPublicidadPdvService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pdv:depurar-publicidad')]
#[Description('Elimina publicidad de sala vencida cuyo periodo de conservación ya terminó')]
class DepurarPublicidadPdvCommand extends Command
{
    public function handle(DepurarPublicidadPdvService $service): int
    {
        $resultado = $service->ejecutar();

        $this->info(sprintf(
            'Publicidad depurada: piezas=%d, archivos=%d, archivos ausentes=%d',
            $resultado['piezas'],
            $resultado['archivos'],
            $resultado['ausentes'],
        ));

        return self::SUCCESS;
    }
}
