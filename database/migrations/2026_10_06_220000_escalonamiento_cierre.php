<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('clientes', 'escalonamiento_meses_sin_compra')) {
                $table->unsignedTinyInteger('escalonamiento_meses_sin_compra')->default(0)->after('lista_bloqueada');
            }
            if (! Schema::hasColumn('clientes', 'escalonamiento_ultima_compra_periodo_id')) {
                $table->foreignId('escalonamiento_ultima_compra_periodo_id')
                    ->nullable()
                    ->after('escalonamiento_meses_sin_compra')
                    ->constrained('escalonamiento_periodos', indexName: 'clientes_esc_ult_compra_periodo_fk')
                    ->nullOnDelete();
            }
        });

        Schema::table('escalonamiento_periodos', function (Blueprint $table) {
            if (! Schema::hasColumn('escalonamiento_periodos', 'escalonamiento_cierre_vigente_id')) {
                $table->unsignedBigInteger('escalonamiento_cierre_vigente_id')->nullable()->after('fecha_corte');
            }
        });

        Schema::create('escalonamiento_cierres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escalonamiento_periodo_id')
                ->constrained('escalonamiento_periodos', indexName: 'esc_cierre_periodo_fk');
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('estado')->default('borrador');
            $table->string('hash_snapshot', 64);
            $table->timestamp('simulado_en');
            $table->foreignId('simulado_por_user_id')
                ->nullable()
                ->constrained('users', indexName: 'esc_cierre_sim_user_fk')
                ->nullOnDelete();
            $table->timestamp('autorizado_en')->nullable();
            $table->foreignId('autorizado_por_user_id')
                ->nullable()
                ->constrained('users', indexName: 'esc_cierre_aut_user_fk')
                ->nullOnDelete();
            $table->timestamp('aplicado_en')->nullable();
            $table->foreignId('aplicado_por_user_id')
                ->nullable()
                ->constrained('users', indexName: 'esc_cierre_apl_user_fk')
                ->nullOnDelete();
            $table->string('reporte_ruta')->nullable();
            $table->timestamp('reporte_generado_en')->nullable();
            $table->boolean('aplicacion_externa_declarada')->default(false);
            $table->timestamp('aplicacion_externa_en')->nullable();
            $table->text('aplicacion_externa_evidencia')->nullable();
            $table->unsignedBigInteger('ultimo_cliente_aplicado_id')->nullable();
            $table->timestamps();

            $table->unique(['escalonamiento_periodo_id', 'version'], 'esc_cierre_periodo_version_unico');
            $table->index(['escalonamiento_periodo_id', 'estado'], 'esc_cierre_periodo_estado_idx');
        });

        Schema::table('escalonamiento_periodos', function (Blueprint $table) {
            if (Schema::hasColumn('escalonamiento_periodos', 'escalonamiento_cierre_vigente_id')) {
                $table->foreign('escalonamiento_cierre_vigente_id', 'esc_periodo_cierre_vigente_fk')
                    ->references('id')
                    ->on('escalonamiento_cierres')
                    ->nullOnDelete();
            }
        });

        Schema::create('escalonamiento_cierre_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escalonamiento_cierre_id')
                ->constrained('escalonamiento_cierres', indexName: 'esc_cierre_det_cierre_fk')
                ->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->decimal('compras', 14, 2)->default(0);
            $table->decimal('devoluciones', 14, 2)->default(0);
            $table->decimal('neto', 14, 2)->default(0);
            $table->foreignId('lista_base_id')->nullable()->constrained('catalogo_listas_descuento', indexName: 'esc_cierre_det_base_fk')->nullOnDelete();
            $table->foreignId('lista_vigente_cierre_id')->nullable()->constrained('catalogo_listas_descuento', indexName: 'esc_cierre_det_vig_fk')->nullOnDelete();
            $table->foreignId('clasificacion_mes_id')->nullable()->constrained('catalogo_listas_descuento', indexName: 'esc_cierre_det_clas_fk')->nullOnDelete();
            $table->foreignId('lista_siguiente_id')->nullable()->constrained('catalogo_listas_descuento', indexName: 'esc_cierre_det_sig_fk')->nullOnDelete();
            $table->foreignId('lista_operativa_id')->nullable()->constrained('catalogo_listas_descuento', indexName: 'esc_cierre_det_op_fk')->nullOnDelete();
            $table->string('motivo')->default('sin_cambio');
            $table->boolean('aplica_cambio_lista')->default(false);
            $table->boolean('propone_inactivo')->default(false);
            $table->unsignedTinyInteger('meses_sin_compra')->default(0);
            $table->json('extras')->nullable();
            $table->timestamps();

            $table->unique(['escalonamiento_cierre_id', 'cliente_id'], 'esc_cierre_det_cliente_unico');
        });

        Schema::create('escalonamiento_cambios_lista', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escalonamiento_cierre_id')
                ->constrained('escalonamiento_cierres', indexName: 'esc_cambio_lista_cierre_fk');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('lista_anterior_id')->nullable()->constrained('catalogo_listas_descuento', indexName: 'esc_cambio_lista_ant_fk')->nullOnDelete();
            $table->foreignId('lista_nueva_id')->nullable()->constrained('catalogo_listas_descuento', indexName: 'esc_cambio_lista_nueva_fk')->nullOnDelete();
            $table->decimal('monto_anterior', 14, 2)->default(0);
            $table->string('motivo');
            $table->timestamp('aplicado_en');
            $table->string('idempotency_key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalonamiento_cambios_lista');
        Schema::dropIfExists('escalonamiento_cierre_detalles');

        Schema::table('escalonamiento_periodos', function (Blueprint $table) {
            if (Schema::hasColumn('escalonamiento_periodos', 'escalonamiento_cierre_vigente_id')) {
                $table->dropForeign('esc_periodo_cierre_vigente_fk');
            }
        });

        Schema::dropIfExists('escalonamiento_cierres');

        Schema::table('escalonamiento_periodos', function (Blueprint $table) {
            if (Schema::hasColumn('escalonamiento_periodos', 'escalonamiento_cierre_vigente_id')) {
                $table->dropColumn('escalonamiento_cierre_vigente_id');
            }
        });

        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes', 'escalonamiento_ultima_compra_periodo_id')) {
                $table->dropConstrainedForeignId('escalonamiento_ultima_compra_periodo_id');
            }
            if (Schema::hasColumn('clientes', 'escalonamiento_meses_sin_compra')) {
                $table->dropColumn('escalonamiento_meses_sin_compra');
            }
        });
    }
};
