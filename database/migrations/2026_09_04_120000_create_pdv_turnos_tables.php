<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->string('folio', 32);
            $table->string('servicio', 32);
            $table->string('origen', 32);
            $table->string('estado', 32);
            $table->string('prioridad', 32)->default('normal');
            $table->boolean('prioridad_adulto_mayor')->default(false);
            $table->boolean('prioridad_discapacidad')->default(false);
            $table->boolean('prioridad_diamante')->default(false);
            $table->boolean('prioridad_vip')->default(false);
            $table->string('snapshot_nombre_llamado');
            $table->string('snapshot_cliente_nombre')->nullable();
            $table->json('snapshot_json')->nullable();
            $table->timestamp('alta_at');
            $table->timestamp('cerrado_at')->nullable();
            $table->timestamp('reatencion_expira_at')->nullable();
            $table->foreignId('alta_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('baja_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('baja_at')->nullable();
            $table->string('baja_motivo', 64)->nullable();
            $table->text('baja_motivo_detalle')->nullable();
            $table->foreignId('atencion_actual_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['sucursal_id', 'folio'], 'pdv_turnos_sucursal_folio_unique');
            $table->index(['sucursal_id', 'estado', 'alta_at'], 'pdv_turnos_sucursal_estado_alta_idx');
            $table->index(['sucursal_id', 'cliente_id', 'estado'], 'pdv_turnos_sucursal_cliente_estado_idx');
            $table->index('reatencion_expira_at');
        });

        Schema::create('pdv_turno_atenciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained('pdv_turnos')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('numero_secuencia')->default(1);
            $table->timestamp('inicio_at');
            $table->timestamp('fin_at')->nullable();
            $table->string('motivo_cierre', 64)->nullable();
            $table->text('motivo_cierre_detalle')->nullable();
            $table->boolean('es_transferencia')->default(false);
            $table->foreignId('transferido_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['turno_id', 'numero_secuencia'], 'pdv_atenciones_turno_secuencia_unique');
            $table->index(['turno_id', 'fin_at'], 'pdv_atenciones_turno_fin_idx');
            $table->index(['user_id', 'fin_at'], 'pdv_atenciones_user_fin_idx');
        });

        Schema::table('pdv_turnos', function (Blueprint $table) {
            $table->foreign('atencion_actual_id')
                ->references('id')
                ->on('pdv_turno_atenciones')
                ->nullOnDelete();
        });

        Schema::create('pdv_turno_prorrogas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atencion_id')->constrained('pdv_turno_atenciones')->restrictOnDelete();
            $table->timestamp('referencia_inicio_at');
            $table->timestamp('alertado_at');
            $table->json('snapshot_json')->nullable();
            $table->timestamps();

            $table->unique('atencion_id', 'pdv_prorrogas_atencion_unique');
            $table->index('alertado_at');
        });

        Schema::create('pdv_turno_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained('pdv_turnos')->restrictOnDelete();
            $table->foreignId('atencion_id')->nullable()->constrained('pdv_turno_atenciones')->nullOnDelete();
            $table->string('tipo_evento', 64);
            $table->string('estado_anterior', 32)->nullable();
            $table->string('estado_nuevo', 32)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ocurrido_at');
            $table->json('snapshot_json')->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamps();

            $table->unique('idempotency_key', 'pdv_turno_eventos_idempotency_unique');
            $table->index(['turno_id', 'ocurrido_at'], 'pdv_turno_eventos_turno_ocurrido_idx');
        });

        Schema::create('pdv_contadores_folio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->date('fecha_operativa');
            $table->string('servicio', 32);
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(
                ['sucursal_id', 'fecha_operativa', 'servicio'],
                'pdv_contadores_folio_clave_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('pdv_turnos', function (Blueprint $table) {
            $table->dropForeign(['atencion_actual_id']);
        });

        Schema::dropIfExists('pdv_contadores_folio');
        Schema::dropIfExists('pdv_turno_eventos');
        Schema::dropIfExists('pdv_turno_prorrogas');
        Schema::dropIfExists('pdv_turno_atenciones');
        Schema::dropIfExists('pdv_turnos');
    }
};
