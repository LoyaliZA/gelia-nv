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
        Schema::table('cobranza_facturas', function (Blueprint $table) {
            $table->boolean('pago_pendiente_confirmacion')->default(false)->after('tiene_abono');
            $table->timestamp('detectado_en_import_at')->nullable()->after('pago_pendiente_confirmacion');
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            'cobranza.confirmar_pago',
            'cobranza.recalcular_creditos',
        ];

        foreach ($permisos as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        }

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permisos);
        }
    }

    public function down(): void
    {
        Schema::table('cobranza_facturas', function (Blueprint $table) {
            $table->dropColumn(['pago_pendiente_confirmacion', 'detectado_en_import_at']);
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', ['cobranza.confirmar_pago', 'cobranza.recalcular_creditos'])
            ->where('guard_name', 'web')
            ->delete();
    }
};
