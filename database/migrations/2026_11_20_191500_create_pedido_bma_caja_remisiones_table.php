<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_bma_caja_remisiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_caja_id')->constrained('pedido_bma_cajas')->cascadeOnDelete();
            $table->foreignId('pedido_bma_id')->nullable()->constrained('pedidos_bma')->nullOnDelete();
            $table->uuid('solicitud_id')->unique();
            $table->string('folio_externo')->nullable();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->text('contenido')->nullable();
            $table->unsignedBigInteger('evidencia_documento_id')->nullable();
            $table->decimal('valor_asegurado', 12, 2)->default(0);
            $table->decimal('mercancia_nueva_por_cobrar', 12, 2)->default(0);
            $table->boolean('cabe')->default(true);
            $table->boolean('activa')->default(true);
            $table->string('exclusividad_activa')->nullable();
            $table->timestamp('sustituida_at')->nullable();
            $table->timestamps();
            $table->index(['pedido_bma_caja_id', 'activa']);
            $table->unique('exclusividad_activa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_bma_caja_remisiones');
    }
};
