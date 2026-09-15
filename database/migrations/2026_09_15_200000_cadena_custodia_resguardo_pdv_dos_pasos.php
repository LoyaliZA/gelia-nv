<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_resguardos', function (Blueprint $table) {
            $table->timestamp('custodia_confirmada_at')->nullable()->after('recepcion_fisica_at');
        });

        Schema::table('pdv_resguardo_bultos', function (Blueprint $table) {
            $table->timestamp('custodia_at')->nullable()->after('recepcion_por_id');
            $table->foreignId('custodia_por_id')->nullable()->after('custodia_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->string('estatus_sucursal', 48)->nullable()->after('estatus_envio');
            $table->index('estatus_sucursal', 'pedidos_bma_estatus_sucursal_idx');
        });

        DB::table('pdv_resguardo_bultos')
            ->where('estado', 'recibido')
            ->update(['estado' => 'en_custodia']);

        DB::table('pdv_resguardos')
            ->where('estado', 'en_custodia')
            ->whereNull('custodia_confirmada_at')
            ->whereNotNull('recepcion_fisica_at')
            ->update(['custodia_confirmada_at' => DB::raw('recepcion_fisica_at')]);

        $permisos = [
            'pdv.resguardos.recibir_gerente' => 'PDV: recibir resguardo como gerente de piso',
            'pdv.resguardos.confirmar_custodia' => 'PDV: confirmar custodia de resguardo',
        ];

        foreach ($permisos as $nombre => $descripcion) {
            Permission::findOrCreate($nombre, 'web');
        }

        $usuariosConRecibir = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('permissions.name', 'pdv.resguardos.recibir')
            ->pluck('model_has_permissions.model_id');

        foreach ($usuariosConRecibir as $userId) {
            foreach (array_keys($permisos) as $permiso) {
                $permissionId = Permission::findByName($permiso, 'web')->id;
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $userId,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('pdv_resguardo_bultos')
            ->where('estado', 'en_custodia')
            ->update(['estado' => 'recibido']);

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropIndex('pedidos_bma_estatus_sucursal_idx');
            $table->dropColumn('estatus_sucursal');
        });

        Schema::table('pdv_resguardo_bultos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('custodia_por_id');
            $table->dropColumn('custodia_at');
        });

        Schema::table('pdv_resguardos', function (Blueprint $table) {
            $table->dropColumn('custodia_confirmada_at');
        });

        Permission::whereIn('name', [
            'pdv.resguardos.recibir_gerente',
            'pdv.resguardos.confirmar_custodia',
        ])->delete();
    }
};
