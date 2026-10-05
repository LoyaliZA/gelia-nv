<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        PermisoCatalogoMigracion::registrar([
            'visitas_programadas.gestionar',
            PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_VER,
            PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_CONFIRMAR_LLEGADA,
        ]);
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', [
            'visitas_programadas.gestionar',
            PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_VER,
            PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_CONFIRMAR_LLEGADA,
        ])->where('guard_name', 'web')->delete();
    }
};
