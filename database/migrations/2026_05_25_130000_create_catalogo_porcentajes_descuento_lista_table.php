<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_porcentajes_escalonamiento_lista', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('catalogo_lista_descuento_id');
            $table->unique('catalogo_lista_descuento_id', 'uq_pct_lista_descuento_id');
            $table->foreign('catalogo_lista_descuento_id', 'fk_pct_lista_descuento_id')
                ->references('id')
                ->on('catalogo_listas_descuento')
                ->cascadeOnDelete();
            $table->decimal('porcentaje_descuento', 5, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_porcentajes_escalonamiento_lista');
    }
};
