<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_jornadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->string('estado', 32);
            $table->timestamp('apertura_at');
            $table->timestamp('cierre_at')->nullable();
            $table->char('jornada_activa_marcador', 1)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(
                ['user_id', 'sucursal_id', 'jornada_activa_marcador'],
                'pdv_jornadas_user_sucursal_activa_unique'
            );
            $table->index(['sucursal_id', 'estado'], 'pdv_jornadas_sucursal_estado_idx');
            $table->index(['user_id', 'sucursal_id', 'apertura_at'], 'pdv_jornadas_user_sucursal_apertura_idx');
        });

        Schema::create('pdv_intervalos_operativos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jornada_id')->constrained('pdv_jornadas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->string('tipo', 32);
            $table->foreignId('atencion_id')->nullable()->constrained('pdv_turno_atenciones')->nullOnDelete();
            $table->timestamp('inicio_at');
            $table->timestamp('fin_at')->nullable();
            $table->char('intervalo_abierto_marcador', 1)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(
                ['user_id', 'sucursal_id', 'intervalo_abierto_marcador'],
                'pdv_intervalos_user_sucursal_abierto_unique'
            );
            $table->index(['jornada_id', 'fin_at'], 'pdv_intervalos_jornada_fin_idx');
            $table->index(['jornada_id', 'inicio_at'], 'pdv_intervalos_jornada_inicio_idx');
        });

        Schema::create('pdv_sucursal_dias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->date('fecha_operativa');
            $table->time('hora_cierre')->nullable();
            $table->boolean('acepta_altas')->default(true);
            $table->timestamp('cierre_manual_at')->nullable();
            $table->foreignId('cierre_manual_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('cierre_automatico_invalidado')->default(false);
            $table->timestamp('ampliacion_hasta_at')->nullable();
            $table->foreignId('ampliacion_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(
                ['sucursal_id', 'fecha_operativa'],
                'pdv_sucursal_dias_sucursal_fecha_unique'
            );
            $table->index(['sucursal_id', 'acepta_altas'], 'pdv_sucursal_dias_sucursal_altas_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_sucursal_dias');
        Schema::dropIfExists('pdv_intervalos_operativos');
        Schema::dropIfExists('pdv_jornadas');
    }
};
