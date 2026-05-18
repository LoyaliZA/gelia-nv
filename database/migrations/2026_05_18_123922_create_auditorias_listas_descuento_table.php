<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias_listas_descuento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lista_id')->constrained('catalogo_listas_descuento')->cascadeOnDelete();
            
            // Permite nulos porque si el cambio proviene de la terminal/script, no hay usuario autenticado
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->decimal('monto_anterior', 15, 2)->nullable();
            $table->decimal('monto_nuevo', 15, 2)->nullable();
            
            // Registrará si el evento vino de la 'web' o de 'consola'
            $table->string('origen_cambio'); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias_listas_descuento');
    }
};