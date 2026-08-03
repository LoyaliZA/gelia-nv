<?php

namespace App\Console\Commands;

use App\Services\Tiendanube\AuditarImagenesTiendanubeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class AuditarImagenesTiendanubeCommand extends Command
{
    protected $signature = 'tiendanube:auditar-imagenes
                            {--limite=100 : Máximo de imágenes a auditar}
                            {--force : Re-medir aunque ya tengan width/height}';

    protected $description = 'Mide dimensiones de imágenes Tiendanube existentes y marca alertas (<800 o no cuadradas)';

    public function handle(AuditarImagenesTiendanubeService $auditor): int
    {
        if (! Schema::hasColumn('tiendanube_producto_imagenes', 'requiere_revision')) {
            $this->error('Ejecuta primero: php artisan migrate');

            return self::FAILURE;
        }

        $limite = max(1, (int) $this->option('limite'));
        $force = (bool) $this->option('force');

        $this->info($force
            ? "Auditando hasta {$limite} imágenes (force)…"
            : "Auditando hasta {$limite} imágenes sin medidas…");

        $result = $auditor->ejecutar($limite, $force);

        $this->info("Procesadas: {$result['procesadas']}");
        $this->info("Actualizadas: {$result['actualizadas']}");
        $this->info("Fallidas: {$result['fallidas']}");

        return self::SUCCESS;
    }
}
