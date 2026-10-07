<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalonamiento_aplicaciones_devolucion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escalonamiento_periodo_id')
                ->constrained('escalonamiento_periodos', indexName: 'esc_apl_periodo_fk');
            $table->foreignId('documento_devolucion_id')
                ->constrained('documentos_venta', indexName: 'esc_apl_dev_fk');
            $table->foreignId('documento_venta_original_id')
                ->nullable()
                ->constrained('documentos_venta', indexName: 'esc_apl_orig_fk');
            $table->foreignId('documento_remision_vinculada_id')
                ->constrained('documentos_venta', indexName: 'esc_apl_rem_fk');
            $table->decimal('importe', 14, 2);
            $table->string('estado')->default('activa');
            $table->text('evidencia');
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users', indexName: 'esc_apl_user_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['documento_remision_vinculada_id', 'estado'], 'esc_apl_rem_estado_idx');
            $table->index(['documento_devolucion_id', 'estado'], 'esc_apl_dev_estado_idx');
        });

        Schema::table('escalonamiento_movimientos', function (Blueprint $table) {
            $table->foreignId('escalonamiento_aplicacion_devolucion_id')
                ->nullable()
                ->after('operacion')
                ->constrained('escalonamiento_aplicaciones_devolucion', indexName: 'esc_mov_apl_fk')
                ->nullOnDelete();
        });

        Schema::table('escalonamiento_resumenes_cliente', function (Blueprint $table) {
            $table->foreignId('clasificacion_mes_max_id')
                ->nullable()
                ->after('clasificacion_mes_id')
                ->constrained('catalogo_listas_descuento', indexName: 'esc_resumen_clas_max_fk')
                ->nullOnDelete();
        });

        Schema::table('escalonamiento_incidencias', function (Blueprint $table) {
            $table->text('resolucion')->nullable()->after('estado');
            $table->timestamp('resuelto_en')->nullable()->after('resolucion');
            $table->foreignId('resuelto_por_user_id')
                ->nullable()
                ->after('resuelto_en')
                ->constrained('users', indexName: 'esc_inc_resuelto_user_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('escalonamiento_incidencias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resuelto_por_user_id');
            $table->dropColumn(['resolucion', 'resuelto_en']);
        });

        Schema::table('escalonamiento_resumenes_cliente', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clasificacion_mes_max_id');
        });

        Schema::table('escalonamiento_movimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('escalonamiento_aplicacion_devolucion_id');
        });

        Schema::dropIfExists('escalonamiento_aplicaciones_devolucion');
    }
};
