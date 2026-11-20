<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cliente_modalidad_eventos') && ! Schema::hasColumn('cliente_modalidad_eventos', 'motivo')) {
            Schema::table('cliente_modalidad_eventos', function (Blueprint $table) {
                $table->text('motivo')->nullable();
            });
        }

        Permission::findOrCreate('clientes.corregir_modalidad', 'web');
        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        $superAdmin?->givePermissionTo('clientes.corregir_modalidad');
    }

    public function down(): void
    {
        Permission::where('name', 'clientes.corregir_modalidad')->where('guard_name', 'web')->delete();
        if (Schema::hasColumn('cliente_modalidad_eventos', 'motivo')) {
            Schema::table('cliente_modalidad_eventos', function (Blueprint $table) {
                $table->dropColumn('motivo');
            });
        }
    }
};
