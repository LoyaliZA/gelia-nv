<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias_accesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_id', 128)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('dispositivo')->nullable();
            $table->string('plataforma')->nullable();
            $table->string('navegador')->nullable();
            $table->string('ubicacion_ciudad')->nullable();
            $table->string('ubicacion_region')->nullable();
            $table->string('ubicacion_pais')->nullable();
            $table->timestamp('inicio_sesion_at');
            $table->timestamp('ultima_actividad_at');
            $table->timestamp('cierre_sesion_at')->nullable();
            $table->string('motivo_cierre')->nullable();
            $table->unsignedInteger('duracion_activa_segundos')->nullable();
            $table->unsignedInteger('duracion_inactiva_segundos')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'cierre_sesion_at']);
            $table->index('inicio_sesion_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias_accesos');
    }
};
