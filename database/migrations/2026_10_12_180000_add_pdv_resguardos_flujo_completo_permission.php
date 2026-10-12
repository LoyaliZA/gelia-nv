<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        PermisoCatalogoMigracion::registrar([
            PuntoVentaModulo::PERMISO_RESGUARDOS_FLUJO_COMPLETO,
        ]);
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', PuntoVentaModulo::PERMISO_RESGUARDOS_FLUJO_COMPLETO)
            ->where('guard_name', 'web')
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
