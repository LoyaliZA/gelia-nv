<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            // Solo agregamos la columna faltante para la lista de descuento
            $table->unsignedBigInteger('catalogo_lista_descuento_id')->nullable()->after('catalogo_tipo_cliente_id');
            
            // Relación foránea
            $table->foreign('catalogo_lista_descuento_id')->references('id')->on('catalogo_listas_descuento')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->dropForeign(['catalogo_lista_descuento_id']);
            $table->dropColumn('catalogo_lista_descuento_id');
        });
    }
};