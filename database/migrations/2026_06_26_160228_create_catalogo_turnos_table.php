<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('catalogo_turnos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->json('matriz_horario');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Insert default schedule
        $matrizEstandar = json_encode([
            'lunes' => ['entrada' => '09:00', 'salida' => '18:00', 'horas' => 9, 'descanso' => false],
            'martes' => ['entrada' => '09:00', 'salida' => '18:00', 'horas' => 9, 'descanso' => false],
            'miercoles' => ['entrada' => '09:00', 'salida' => '18:00', 'horas' => 9, 'descanso' => false],
            'jueves' => ['entrada' => '09:00', 'salida' => '18:00', 'horas' => 9, 'descanso' => false],
            'viernes' => ['entrada' => '09:00', 'salida' => '18:00', 'horas' => 9, 'descanso' => false],
            'sabado' => ['entrada' => '09:00', 'salida' => '14:00', 'horas' => 5, 'descanso' => false],
            'domingo' => ['entrada' => '00:00', 'salida' => '00:00', 'horas' => 0, 'descanso' => true],
        ]);

        DB::table('catalogo_turnos')->insert([
            'nombre' => 'Estándar (L-V 9h, S 5h)',
            'matriz_horario' => $matrizEstandar,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('rh_colaboradores', function (Blueprint $table) {
            $table->foreignId('catalogo_turno_id')->default(1)->after('catalogo_puesto_id')->constrained('catalogo_turnos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rh_colaboradores', function (Blueprint $table) {
            $table->dropForeign(['catalogo_turno_id']);
            $table->dropColumn('catalogo_turno_id');
        });

        Schema::dropIfExists('catalogo_turnos');
    }
};
