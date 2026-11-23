<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISO = 'punto_venta.resguardos.salida_terminal';

    public function up(): void
    {
        Permission::findOrCreate(self::PERMISO, 'web');
        // Solo Super Admin; Gerencia PDV y demás roles se asignan manualmente.
        Role::where('name', 'Super Admin')->where('guard_name', 'web')->first()?->givePermissionTo(self::PERMISO);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'web')->first()?->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
