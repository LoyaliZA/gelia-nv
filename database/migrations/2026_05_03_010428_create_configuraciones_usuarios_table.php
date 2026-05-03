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
        Schema::create('configuraciones_usuarios', function (Blueprint $table) {
            $table->id();
            
            // Relación estricta 1 a 1 con el usuario
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Columna JSON para almacenar un objeto con todas las variables de diseño
            // Ej: {"modo": "dark", "color_hover": "#ff00bb", "fondo_url": "imagen.jpg"}
            $table->json('tema_visual')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuraciones_usuarios');
    }
};