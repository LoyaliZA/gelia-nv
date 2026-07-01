<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('errores_importacion_clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacion_cliente_id')
                ->constrained('importaciones_clientes')
                ->cascadeOnDelete();
            $table->unsignedInteger('numero_fila')->nullable();
            $table->string('numero_cliente')->nullable();
            $table->text('mensaje');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('errores_importacion_clientes');
    }
};
