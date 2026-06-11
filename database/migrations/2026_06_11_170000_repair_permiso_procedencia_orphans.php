<?php

use App\Services\Permisos\RepararProcedenciaPermisosService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        RepararProcedenciaPermisosService::repararHuerfanos();
    }

    public function down(): void
    {
        // No revertir: los registros reparados reflejan el estado real de permisos existentes.
    }
};
