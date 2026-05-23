<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla de Configuraciones Globales
        Schema::create('gelia_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->timestamps();
        });

        // 2. Tabla de Listas Personalizadas
        Schema::create('custom_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // El autor/dueño
            $table->string('nombre_creador');
            $table->string('titulo_lista');
            $table->text('descripcion')->nullable();
            $table->string('color')->default('blue');
            $table->json('archivos_requeridos');
            $table->json('columnas_exportar');
            $table->string('nombre_archivo_salida');
            $table->boolean('solo_con_existencia')->default(false);
            $table->boolean('filtro_relojes')->default(false);
            $table->decimal('pct_venta_especial', 5, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // 3. Tabla Pivote para Compartir Listas (Muchos a Muchos)
        Schema::create('custom_list_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_list_id')->constrained('custom_lists')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Usuario con el que se comparte
            $table->timestamps();
            
            $table->unique(['custom_list_id', 'user_id']); // Evitar duplicados
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_list_user');
        Schema::dropIfExists('custom_lists');
        Schema::dropIfExists('gelia_settings');
    }
};