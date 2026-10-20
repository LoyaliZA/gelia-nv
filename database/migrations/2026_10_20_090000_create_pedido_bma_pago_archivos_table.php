<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_bma_pago_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_pago_id')
                ->constrained('pedido_bma_pagos')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('orden');
            $table->string('ruta_archivo');
            $table->string('nombre_original')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('tamano_bytes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_bma_pago_archivos');
    }
};
