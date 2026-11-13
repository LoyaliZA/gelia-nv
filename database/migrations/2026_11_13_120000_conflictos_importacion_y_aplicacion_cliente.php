<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conflictos_importacion_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacion_cliente_id')->nullable()->constrained('importaciones_clientes')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('numero_cliente');
            $table->unsignedInteger('numero_fila')->nullable();
            $table->string('alcance', 32);
            $table->string('estado', 32)->default('pendiente');
            $table->text('motivo');
            $table->json('valor_vigente')->nullable();
            $table->json('valor_propuesto')->nullable();
            $table->string('origen_vigente')->nullable();
            $table->string('origen_propuesto')->nullable();
            $table->unsignedBigInteger('solicitud_tag_id')->nullable();
            $table->foreignId('resuelto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 32)->nullable();
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamps();
            $table->index(['estado', 'alcance']);
        });

        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->string('aplicacion_cliente', 32)->nullable();
            $table->text('aplicacion_error')->nullable();
            $table->unsignedSmallInteger('aplicacion_intentos')->default(0);
        });

        $rolId = DB::table('roles')->where('name', 'Super Admin')->where('guard_name', 'web')->value('id');
        foreach (['clientes.recuperar_aplicacion_autorizada', 'clientes.resolver_conflicto_importacion'] as $nombre) {
            $permisoId = DB::table('permissions')->where('name', $nombre)->where('guard_name', 'web')->value('id');
            if (! $permisoId) {
                $permisoId = DB::table('permissions')->insertGetId([
                    'name' => $nombre,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($rolId && ! DB::table('role_has_permissions')->where('permission_id', $permisoId)->where('role_id', $rolId)->exists()) {
                DB::table('role_has_permissions')->insert(['permission_id' => $permisoId, 'role_id' => $rolId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->dropColumn(['aplicacion_cliente', 'aplicacion_error', 'aplicacion_intentos']);
        });
        Schema::dropIfExists('conflictos_importacion_cliente');
    }
};
