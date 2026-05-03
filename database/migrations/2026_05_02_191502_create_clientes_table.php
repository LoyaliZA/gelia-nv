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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            // Indexado para busquedas rapidas al crear solicitudes
            $table->string('numero_cliente')->unique();
            $table->string('nombre');
            
            // Llaves foraneas
            $table->foreignId('lista_actual_id')
                  ->constrained('catalogo_listas_descuento')
                  ->restrictOnDelete();
                  
            // Se asume la existencia previa de la tabla users por defecto en Laravel
            $table->foreignId('vendedor_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Metricas y reglas de negocio
            $table->decimal('monto_venta_actual', 12, 2)->default(0.00);
            $table->boolean('es_heredado')->default(false);
            
            $table->timestamps();
            // Permite mantener el registro historico sin afectar referencias
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};