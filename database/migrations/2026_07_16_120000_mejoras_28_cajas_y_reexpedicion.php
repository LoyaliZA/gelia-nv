<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_tipos_caja_pedido', function (Blueprint $table) {
            $table->decimal('largo', 10, 2)->nullable()->after('medidas');
            $table->decimal('ancho', 10, 2)->nullable()->after('largo');
            $table->decimal('alto', 10, 2)->nullable()->after('ancho');
        });

        Schema::create('catalogo_reexpedicion_pedido', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_postal', 10);
            $table->foreignId('paqueteria_id')->constrained('catalogo_paqueterias_pedido')->restrictOnDelete();
            $table->decimal('costo_adicional', 12, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['codigo_postal', 'paqueteria_id'], 'reexpedicion_cp_paq_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_reexpedicion_pedido');

        Schema::table('catalogo_tipos_caja_pedido', function (Blueprint $table) {
            $table->dropColumn(['largo', 'ancho', 'alto']);
        });
    }
};
