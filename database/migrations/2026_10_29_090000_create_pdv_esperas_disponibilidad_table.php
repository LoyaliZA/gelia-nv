<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_esperas_disponibilidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jornada_id')->constrained('pdv_jornadas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origen', 32);
            $table->unsignedInteger('cooldown_segundos');
            $table->timestamp('disponible_desde_anterior')->nullable();
            $table->timestamp('disponible_desde_nuevo')->nullable();
            $table->foreignId('atencion_id')->nullable()->constrained('pdv_turno_atenciones')->nullOnDelete();
            $table->foreignId('intervalo_id')->nullable()->constrained('pdv_intervalos_operativos')->nullOnDelete();
            $table->timestamp('ocurrido_at');
            $table->timestamps();

            $table->index(
                ['user_id', 'sucursal_id', 'ocurrido_at'],
                'pdv_esperas_user_sucursal_ocurrido_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_esperas_disponibilidad');
    }
};
