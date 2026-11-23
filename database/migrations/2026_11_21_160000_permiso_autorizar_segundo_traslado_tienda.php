<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISO = 'control_pedidos.tienda.autorizar_segundo_traslado';

    public function up(): void
    {
        Permission::findOrCreate(self::PERMISO, 'web');
        // Solo Super Admin; el resto de roles se asigna manualmente (T-03).
        Role::where('name', 'Super Admin')->where('guard_name', 'web')->first()?->givePermissionTo(self::PERMISO);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'web')->first()?->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
