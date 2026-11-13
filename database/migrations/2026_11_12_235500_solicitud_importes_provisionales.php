<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('solicitud_importes_provisionales')) {
            Schema::create('solicitud_importes_provisionales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cliente_id')->constrained('clientes');
                $table->foreignId('solicitud_tag_id')->constrained('solicitudes_tags');
                $table->decimal('importe', 15, 2);
                $table->string('tipo', 32);
                $table->string('estado', 32);
                $table->foreignId('importacion_cliente_id')->nullable()->constrained('importaciones_clientes');
                $table->dateTime('fecha_operacion');
                $table->dateTime('fecha_corte')->nullable();
                $table->string('origen', 64);
                $table->foreignId('usuario_id')->nullable()->constrained('users');
                $table->string('clave_operacion', 80)->unique();
                $table->string('clave_vigente', 80)->nullable()->unique();
                $table->string('inclusion', 32)->nullable();
                $table->json('trazabilidad');
                $table->timestamps();

                $table->index(['cliente_id', 'estado'], 'sol_imp_prov_cliente_estado');
                $table->index(['solicitud_tag_id', 'estado'], 'sol_imp_prov_solicitud_estado');
            });
        }

        if (Schema::hasTable('importaciones_clientes') && ! Schema::hasColumn('importaciones_clientes', 'fecha_corte')) {
            Schema::table('importaciones_clientes', function (Blueprint $table) {
                $table->dateTime('fecha_corte')->nullable();
                $table->string('modalidad_carga', 40)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_importes_provisionales');
        if (Schema::hasTable('importaciones_clientes') && Schema::hasColumn('importaciones_clientes', 'fecha_corte')) {
            Schema::table('importaciones_clientes', function (Blueprint $table) {
                $table->dropColumn(['fecha_corte', 'modalidad_carga']);
            });
        }
    }
};
