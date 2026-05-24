<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_zona_entrega_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->json('coordenadas_poligono');
            $table->foreignId('zona_referencia_id')
                ->constrained('catalogo_zonas_entrega')
                ->cascadeOnDelete();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('prioridad')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_zona_entrega_overrides');
    }
};
