<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte_pagos_pedidos_exportaciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('titulo', 120);
            $table->string('formato', 20);
            $table->string('estado', 20)->default('processing');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('etapa', 40)->nullable();
            $table->string('etapa_label', 80)->nullable();
            $table->unsignedInteger('registros_procesados')->default(0);
            $table->unsignedInteger('registros_total')->default(0);
            $table->string('nombre_archivo', 255)->nullable();
            $table->string('ruta_archivo', 500)->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->unsignedSmallInteger('num_paginas')->nullable();
            $table->unsignedInteger('num_registros')->nullable();
            $table->json('filtros')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('expira_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_pagos_pedidos_exportaciones');
    }
};
