<?php

namespace App\Console\Commands;

use App\Services\Auditoria\RegistrarAuditoriaAccesoService;
use Illuminate\Console\Command;

class SincronizarSesionesExpiradas extends Command
{
    protected $signature = 'sesiones:sincronizar-expiradas';

    protected $description = 'Marca como expiradas las auditorías de acceso cuya sesión ya no existe';

    public function handle(RegistrarAuditoriaAccesoService $auditoriaAcceso): int
    {
        $cerradas = $auditoriaAcceso->sincronizarExpiradas();

        if ($cerradas > 0) {
            $this->info("{$cerradas} acceso(s) marcado(s) como expirados.");
        }

        return self::SUCCESS;
    }
}
