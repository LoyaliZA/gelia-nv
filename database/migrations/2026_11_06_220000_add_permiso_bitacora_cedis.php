<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use App\Support\ControlPedidos\BitacoraCedis;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        PermisoCatalogoMigracion::registrar(BitacoraCedis::PERMISO);
    }

    public function down(): void
    {
        Permission::query()->where('name', BitacoraCedis::PERMISO)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
