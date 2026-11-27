<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impresiones_guias_cedis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pedido_bma_id');
            $table->unsignedBigInteger('pedido_bma_caja_id')->nullable();
            $table->unsignedBigInteger('documento_id')->unique();
            $table->unsignedInteger('orden_caja')->nullable();
            $table->string('nombre_caja')->nullable();
            $table->string('nombre_pdf');
            $table->string('ruta_copia', 500)->nullable();
            $table->timestamp('comunicada_at');
            $table->timestamp('impresion_confirmada_at')->nullable();
            $table->unsignedBigInteger('impresion_confirmada_por_id')->nullable();
            $table->index(['impresion_confirmada_at', 'pedido_bma_id'], 'impresiones_guias_cedis_estado_pedido');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impresiones_guias_cedis');
    }
};
