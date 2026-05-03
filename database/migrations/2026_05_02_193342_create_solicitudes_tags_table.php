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
        Schema::create('solicitudes_tags', function (Blueprint $table) {
            $table->id();
            
            // Llaves foráneas
            // Cliente puede ser nulo si es una prospección o cliente nuevo que no existe en el catálogo aún
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->foreignId('vendedor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('catalogo_proceso_id')->constrained('catalogo_procesos')->restrictOnDelete();
            $table->foreignId('catalogo_estado_solicitud_id')->constrained('catalogo_estados_solicitud')->restrictOnDelete();
            
            // Datos transaccionales
            $table->decimal('monto_cotizado', 12, 2);
            $table->boolean('pago_confirmado')->default(false);
            $table->text('observaciones_vendedor')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_tags');
    }
};