<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $grupoRoleIds = DB::table('roles')
            ->where('name', 'like', 'Grupo:%')
            ->pluck('id');

        if ($grupoRoleIds->isEmpty()) {
            return;
        }

        DB::table('model_has_roles')
            ->whereIn('role_id', $grupoRoleIds)
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No reversible: los roles Grupo: ya no se reasignan automáticamente.
    }
};
