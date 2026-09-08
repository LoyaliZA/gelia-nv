<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_equipo_asistencia_dia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha_operativa');
            $table->timestamp('no_llego_at')->nullable();
            $table->foreignId('no_llego_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(
                ['sucursal_id', 'user_id', 'fecha_operativa'],
                'pdv_equipo_asistencia_dia_unique'
            );
            $table->index(['sucursal_id', 'fecha_operativa'], 'pdv_equipo_asistencia_dia_sucursal_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_equipo_asistencia_dia');
    }
};
