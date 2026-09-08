<?php

use App\Models\User;
use App\Services\Permisos\PermisoCatalogoMigracion;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        PermisoCatalogoMigracion::registrar(PuntoVentaModulo::PERMISO_TURNOS_ATENDER);

        $padre = PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION;
        $nuevo = PuntoVentaModulo::PERMISO_TURNOS_ATENDER;

        Role::query()
            ->where('guard_name', 'web')
            ->whereHas('permissions', fn ($q) => $q->where('name', $padre))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($nuevo));

        User::withoutGlobalScopes()
            ->whereHas('permissions', fn ($q) => $q->where('name', $padre))
            ->get()
            ->each(fn (User $user) => $user->givePermissionTo($nuevo));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', PuntoVentaModulo::PERMISO_TURNOS_ATENDER)
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
