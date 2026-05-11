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
    Schema::table('clientes', function (Blueprint $table) {
        // Relacionamos al cliente con su estado actual del catálogo
        $table->foreignId('catalogo_tipo_cliente_id')
              ->nullable()
              ->after('vendedor_id')
              ->constrained('catalogo_tipo_clientes')
              ->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('clientes', function (Blueprint $table) {
        $table->dropForeign(['catalogo_tipo_cliente_id']);
        $table->dropColumn('catalogo_tipo_cliente_id');
    });
}
};
