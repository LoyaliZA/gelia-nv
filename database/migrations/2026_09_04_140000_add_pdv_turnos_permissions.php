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
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_ALTA,
            PuntoVentaModulo::PERMISO_TURNOS_MARCAR_PRIORIDAD,
        ]);
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', [
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_ALTA,
            PuntoVentaModulo::PERMISO_TURNOS_MARCAR_PRIORIDAD,
        ])->delete();
    }
};
