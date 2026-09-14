<?php

namespace App\Jobs\Tiendanube\Precios;

use App\Services\Tiendanube\Precios\Aplicacion\TiendanubePrecioEjecucionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecuperarPrecioEjecucionesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(TiendanubePrecioEjecucionService $ejecuciones): void
    {
        $ejecuciones->recuperar();
    }
}
