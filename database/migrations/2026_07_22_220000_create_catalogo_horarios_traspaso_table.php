<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_horarios_traspaso', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->unsignedTinyInteger('dias_para_entrega')->default(0);
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        DB::table('catalogo_horarios_traspaso')->insert([
            [
                'nombre' => 'Antes de mediodía',
                'hora_inicio' => '00:00:00',
                'hora_fin' => '12:00:00',
                'dias_para_entrega' => 0,
                'descripcion' => 'Solicitudes antes de las 12:00 llegan el mismo día.',
                'activo' => true,
                'orden' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'Tarde (12:00–16:00)',
                'hora_inicio' => '12:00:00',
                'hora_fin' => '16:00:00',
                'dias_para_entrega' => 1,
                'descripcion' => 'Solicitudes de 12:00 a 16:00 llegan al día siguiente.',
                'activo' => true,
                'orden' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'Fuera de horario',
                'hora_inicio' => '16:00:00',
                'hora_fin' => '23:59:59',
                'dias_para_entrega' => 1,
                'descripcion' => 'Fuera de ventana operativa; se estima entrega al día siguiente (informativo).',
                'activo' => true,
                'orden' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_horarios_traspaso');
    }
};
