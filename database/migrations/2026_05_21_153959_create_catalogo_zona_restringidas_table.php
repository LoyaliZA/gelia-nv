<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_zonas_restringidas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Ej: "Carretera Federal Cárdenas"
            $table->json('coordenadas_poligono');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_zonas_restringidas');
    }
};