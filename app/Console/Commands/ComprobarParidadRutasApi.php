<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route as RutaLaravel;
use Illuminate\Support\Facades\Route;

class ComprobarParidadRutasApi extends Command
{
    protected $signature = 'api:paridad-rutas {--listar : Muestra también las rutas con equivalente}';

    protected $description = 'Verifica que cada ruta web de Punto de Venta y Gestión de pedidos tenga su equivalente en api/v1/mobile.';

    public function handle(): int
    {
        $modulos = (array) config('api_paridad.modulos', []);
        $excluidas = (array) config('api_paridad.excluidas', []);

        $api = [];
        $web = [];

        foreach (Route::getRoutes()->getRoutes() as $ruta) {
            /** @var RutaLaravel $ruta */
            foreach ($ruta->methods() as $metodo) {
                if ($metodo === 'HEAD') {
                    continue;
                }

                $uri = $ruta->uri();
                if (str_starts_with($uri, 'api/v1/mobile/')) {
                    $api[$metodo.' '.substr($uri, strlen('api/v1/mobile/'))] = true;

                    continue;
                }

                foreach ($modulos as $modulo) {
                    if ($uri === $modulo || str_starts_with($uri, $modulo.'/')) {
                        $web[$metodo.' '.$uri] = true;
                    }
                }
            }
        }

        ksort($web);
        $faltantes = [];
        $cubiertas = 0;

        foreach (array_keys($web) as $clave) {
            if (isset($api[$clave]) || isset($excluidas[$clave])) {
                $cubiertas++;
                if ($this->option('listar')) {
                    $this->line('  ok  '.$clave.(isset($excluidas[$clave]) ? '  (excluida)' : ''));
                }

                continue;
            }
            $faltantes[] = $clave;
        }

        $obsoletas = array_values(array_diff(array_keys($excluidas), array_keys($web)));

        $this->info(sprintf('Rutas web revisadas: %d · cubiertas: %d · sin equivalente móvil: %d', count($web), $cubiertas, count($faltantes)));

        foreach ($faltantes as $clave) {
            $this->error('Falta en api/v1/mobile: '.$clave);
        }
        foreach ($obsoletas as $clave) {
            $this->warn('Exclusión obsoleta (la ruta web ya no existe): '.$clave);
        }

        return $faltantes === [] ? self::SUCCESS : self::FAILURE;
    }
}
