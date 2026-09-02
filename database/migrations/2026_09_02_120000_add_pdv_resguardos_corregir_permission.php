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
            PuntoVentaModulo::PERMISO_RESGUARDOS_CORREGIR,
        ]);
    }

    public function down(): void
    {
        Permission::where('name', PuntoVentaModulo::PERMISO_RESGUARDOS_CORREGIR)->delete();
    }
};
