<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_resguardo_exportaciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resguardo_id')->nullable()->constrained('pdv_resguardos')->nullOnDelete();
            $table->string('titulo', 160);
            $table->string('tipo', 20);
            $table->string('estado', 20)->default('pending');
            $table->string('nombre_archivo', 255)->nullable();
            $table->string('ruta_archivo', 500)->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
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
        Schema::dropIfExists('pdv_resguardo_exportaciones');
    }
};
