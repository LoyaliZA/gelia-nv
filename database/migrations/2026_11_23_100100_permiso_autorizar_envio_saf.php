<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISO = 'saldos_favor.autorizar_envio';

    public function up(): void
    {
        Permission::findOrCreate(self::PERMISO, 'web');
        // Solo Super Admin; Administración lo recibe por asignación manual.
        Role::where('name', 'Super Admin')->where('guard_name', 'web')->first()?->givePermissionTo(self::PERMISO);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'web')->first()?->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
