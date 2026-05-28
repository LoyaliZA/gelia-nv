<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activo_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();
            $table->string('ruta');
            $table->string('nombre_original')->nullable();
            $table->unsignedTinyInteger('orden');
            $table->boolean('es_principal')->default(false);
            $table->unsignedInteger('tamano_bytes')->default(0);
            $table->timestamps();

            $table->unique(['activo_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activo_fotos');
    }
};
