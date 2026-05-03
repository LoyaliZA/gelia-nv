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
        Schema::create('auditorias_solicitudes', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('solicitud_id')->constrained('solicitudes_tags')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->comment('El usuario que realiza la acción');
            
            $table->foreignId('estado_anterior_id')->nullable()->constrained('catalogo_estados_solicitud');
            $table->foreignId('estado_nuevo_id')->nullable()->constrained('catalogo_estados_solicitud');
            
            $table->text('motivo_reporte')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditorias_solicitudes');
    }
};