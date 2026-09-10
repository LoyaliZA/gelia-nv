<?php

namespace App\Console\Commands;

use App\Services\Tiendanube\TiendanubeImageImportService;
use Illuminate\Console\Command;

class LimpiarImportacionesImagenesTiendanubeCommand extends Command
{
    protected $signature = 'tiendanube:limpiar-imports-imagenes {--dias= : Antigüedad mínima en días (por defecto config)}';

    protected $description = 'Elimina temporales de importaciones de imágenes Tiendanube ya terminadas';

    public function handle(TiendanubeImageImportService $service): int
    {
        if ($this->option('dias') !== null) {
            $dias = max(1, (int) $this->option('dias'));
            config(['tiendanube.image_import_retention_days' => $dias]);
        }

        $eliminados = $service->limpiarTemporalesVencidos();
        $this->info("Directorios de importación eliminados: {$eliminados}");

        return self::SUCCESS;
    }
}
