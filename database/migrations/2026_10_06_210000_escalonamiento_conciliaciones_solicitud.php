<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('escalonamiento_conciliaciones_solicitud')) {
            Schema::create('escalonamiento_conciliaciones_solicitud', function (Blueprint $table) {
                $table->id();
                $table->foreignId('escalonamiento_periodo_id')
                    ->constrained('escalonamiento_periodos', indexName: 'esc_conc_sol_periodo_fk');
                $table->foreignId('solicitud_tag_id')
                    ->constrained('solicitudes_tags', indexName: 'esc_conc_sol_solicitud_fk');
                $table->foreignId('documento_venta_id')
                    ->nullable()
                    ->constrained('documentos_venta', indexName: 'esc_conc_sol_documento_fk');
                $table->decimal('importe_asignado', 14, 2)->default(0);
                $table->string('estado')->default('pendiente');
                $table->text('evidencia')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->timestamps();

                $table->unique(
                    ['solicitud_tag_id', 'documento_venta_id'],
                    'escalonamiento_conciliacion_solicitud_documento_unico',
                );
            });
        }

        if (! Schema::hasColumn('escalonamiento_incidencias', 'solicitud_tag_id')) {
            Schema::table('escalonamiento_incidencias', function (Blueprint $table) {
                $table->foreignId('solicitud_tag_id')
                    ->nullable()
                    ->after('documento_venta_id')
                    ->constrained('solicitudes_tags', indexName: 'esc_incidencia_solicitud_fk');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('escalonamiento_incidencias', 'solicitud_tag_id')) {
            Schema::table('escalonamiento_incidencias', function (Blueprint $table) {
                $table->dropConstrainedForeignId('solicitud_tag_id');
            });
        }

        Schema::dropIfExists('escalonamiento_conciliaciones_solicitud');
    }
};
