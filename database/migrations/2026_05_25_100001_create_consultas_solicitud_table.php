<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultas_solicitud', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes_tags')->cascadeOnDelete();
            $table->foreignId('vendedor_id')->constrained('users');
            $table->boolean('consulta_tag')->default(false);
            $table->boolean('consulta_lista')->default(false);
            $table->text('comentario_vendedor')->nullable();
            $table->string('estado')->default('pendiente');
            $table->boolean('respuesta_positiva')->nullable();
            $table->text('comentario_encargada')->nullable();
            $table->string('evidencia_respuesta_path')->nullable();
            $table->foreignId('encargada_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultas_solicitud');
    }
};
