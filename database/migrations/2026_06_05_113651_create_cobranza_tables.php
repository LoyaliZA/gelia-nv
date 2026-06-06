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
        Schema::create('cobranza_facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('folio')->index();
            $table->decimal('monto', 15, 2);
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->index();
            $table->boolean('pagada')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('cobranza_alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('factura_id')->constrained('cobranza_facturas')->cascadeOnDelete();
            $table->integer('dias_atraso');
            $table->date('fecha_alerta')->index();
            $table->string('estado')->default('pendiente')->index(); // 'pendiente', 'llamado', 'no_contesto', 'compromiso_pago'
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cobranza_alertas');
        Schema::dropIfExists('cobranza_facturas');
    }
};
