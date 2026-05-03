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
        Schema::create('historial_montos_clientes', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            
            $table->decimal('monto_anterior', 12, 2)->default(0.00);
            $table->decimal('monto_nuevo', 12, 2);
            $table->decimal('diferencia_aplicada', 12, 2);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_montos_clientes');
    }
};