<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->string('evidencia_path')->nullable()->after('observaciones_vendedor');
            // También agregamos el tipo de cliente por si acaso no lo habías corrido en la tabla correcta
            if (!Schema::hasColumn('solicitudes_tags', 'catalogo_tipo_cliente_id')) {
                $table->foreignId('catalogo_tipo_cliente_id')->nullable()->after('catalogo_proceso_id')->constrained('catalogo_tipo_clientes')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->dropColumn('evidencia_path');
        });
    }
};
