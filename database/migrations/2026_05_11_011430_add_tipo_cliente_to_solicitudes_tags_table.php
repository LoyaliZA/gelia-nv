<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            // Vinculamos la solicitud con el nuevo catálogo
            $table->foreignId('catalogo_tipo_cliente_id')->nullable()->after('catalogo_proceso_id')
                  ->constrained('catalogo_tipo_clientes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->dropForeign(['catalogo_tipo_cliente_id']);
            $table->dropColumn('catalogo_tipo_cliente_id');
        });
    }
};