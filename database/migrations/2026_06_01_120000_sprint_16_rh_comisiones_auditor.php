<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rh_comisiones_auditor')) {
            Schema::create('rh_comisiones_auditor', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('rh_deduccion_id')->constrained('rh_deducciones')->cascadeOnDelete();
                $table->foreignId('catalogo_regla_incidencia_id')->nullable()->constrained('catalogo_reglas_incidencia')->nullOnDelete();
                $table->decimal('monto', 12, 2);
                $table->enum('estado', ['pendiente', 'pagado'])->default('pendiente');
                $table->timestamp('fecha_acreditacion')->useCurrent();
                $table->timestamps();

                $table->index(['user_id', 'estado'], 'rh_com_auditor_user_estado_idx');
                $table->unique('rh_deduccion_id', 'rh_com_auditor_deduccion_uq');
            });
        }

        if (!Schema::hasColumn('rh_colaboradores', 'saldo_comisiones')) {
            Schema::table('rh_colaboradores', function (Blueprint $table) {
                $table->decimal('saldo_comisiones', 12, 2)->default(0)->after('salario_por_minuto');
            });
        }

        if (!Schema::hasTable('rh_movimientos_comision_colaborador')) {
            Schema::create('rh_movimientos_comision_colaborador', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rh_colaborador_id')->constrained('rh_colaboradores')->cascadeOnDelete();
                $table->foreignId('rh_deduccion_id')->nullable()->constrained('rh_deducciones')->nullOnDelete();
                $table->enum('tipo', ['cargo', 'abono'])->default('cargo');
                $table->decimal('monto', 12, 2);
                $table->decimal('saldo_resultante', 12, 2)->default(0);
                $table->string('concepto')->nullable();
                $table->timestamps();

                $table->index(['rh_colaborador_id', 'created_at'], 'rh_mov_com_colab_fecha_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_movimientos_comision_colaborador');

        if (Schema::hasColumn('rh_colaboradores', 'saldo_comisiones')) {
            Schema::table('rh_colaboradores', function (Blueprint $table) {
                $table->dropColumn('saldo_comisiones');
            });
        }

        Schema::dropIfExists('rh_comisiones_auditor');
    }
};
