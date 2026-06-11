<?php

use App\Services\Permisos\RevocarPermisosAsignadosPorMigracionService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        RevocarPermisosAsignadosPorMigracionService::ejecutar();
    }

    public function down(): void
    {
        // No restaurar: los permisos deben asignarse manualmente.
    }
};
