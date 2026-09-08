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
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER,
            PuntoVentaModulo::PERMISO_TURNOS_REATENCION_ASIGNAR,
            PuntoVentaModulo::PERMISO_TURNOS_ALERTAS_SUCURSAL,
            PuntoVentaModulo::PERMISO_PANTALLA_SALA_ABRIR,
        ]);
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', [
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER,
            PuntoVentaModulo::PERMISO_TURNOS_REATENCION_ASIGNAR,
            PuntoVentaModulo::PERMISO_TURNOS_ALERTAS_SUCURSAL,
            PuntoVentaModulo::PERMISO_PANTALLA_SALA_ABRIR,
        ])->delete();
    }
};
