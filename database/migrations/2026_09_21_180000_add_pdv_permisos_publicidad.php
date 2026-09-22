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
            PuntoVentaModulo::PERMISO_PUBLICIDAD_VER,
            PuntoVentaModulo::PERMISO_PUBLICIDAD_CREAR,
            PuntoVentaModulo::PERMISO_PUBLICIDAD_EDITAR,
            PuntoVentaModulo::PERMISO_PUBLICIDAD_ELIMINAR,
            PuntoVentaModulo::PERMISO_PUBLICIDAD_ORDENAR,
        ]);
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', [
            PuntoVentaModulo::PERMISO_PUBLICIDAD_VER,
            PuntoVentaModulo::PERMISO_PUBLICIDAD_CREAR,
            PuntoVentaModulo::PERMISO_PUBLICIDAD_EDITAR,
            PuntoVentaModulo::PERMISO_PUBLICIDAD_ELIMINAR,
            PuntoVentaModulo::PERMISO_PUBLICIDAD_ORDENAR,
        ])->delete();
    }
};
