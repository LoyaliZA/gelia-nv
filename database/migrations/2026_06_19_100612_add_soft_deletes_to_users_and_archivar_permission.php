<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'usuarios.archivar', 'guard_name' => 'web']);

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo('usuarios.archivar');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->revokePermissionTo('usuarios.archivar');
        }

        Permission::where('name', 'usuarios.archivar')->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
