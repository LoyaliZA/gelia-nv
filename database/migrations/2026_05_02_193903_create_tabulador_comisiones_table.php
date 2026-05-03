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
        Schema::create('tabulador_comisiones', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('catalogo_proceso_id')
                  ->constrained('catalogo_procesos')
                  ->cascadeOnDelete();
                  
            $table->decimal('monto_comision', 10, 2);
            // Bandera para identificar la comisión vigente sin borrar las anteriores
            $table->boolean('activo')->default(true); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tabulador_comisiones');
    }
};