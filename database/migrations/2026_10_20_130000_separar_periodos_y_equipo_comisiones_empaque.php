<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comisiones_empaque_repartos', function (Blueprint $table) {
            $table->boolean('permanente')->default(false)->index();
        });
        Schema::create('comisiones_empaque_periodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('comisiones_empaque_areas')->restrictOnDelete();
            $table->string('nombre');
            $table->dateTime('desde');
            $table->dateTime('hasta'); // Final exclusivo.
            $table->text('motivo')->nullable();
            $table->foreignId('reparto_origen_id')->nullable()->constrained('comisiones_empaque_repartos')->restrictOnDelete();
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['area_id', 'desde', 'hasta']);
        });
        // Los repartos y asignaciones anteriores se conservan íntegros.
        foreach (DB::table('comisiones_empaque_repartos')->whereNotNull('nombre')->get() as $anterior) {
            if ($anterior->vigente_hasta) {
                DB::table('comisiones_empaque_periodos')->insert([
                    'area_id' => $anterior->area_id, 'nombre' => $anterior->nombre,
                    'desde' => $anterior->vigente_desde, 'hasta' => $anterior->vigente_hasta,
                    'motivo' => $anterior->motivo, 'reparto_origen_id' => $anterior->id,
                    'creado_por_id' => $anterior->creado_por_id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        foreach (DB::table('comisiones_empaque_areas')->pluck('id') as $areaId) {
            $anterior = DB::table('comisiones_empaque_repartos')->where('area_id', $areaId)->orderByDesc('vigente_desde')->orderByDesc('id')->first();
            if (! $anterior) {
                continue;
            }
            $id = DB::table('comisiones_empaque_repartos')->insertGetId([
                'area_id' => $areaId, 'permanente' => true, 'nombre' => null,
                'motivo' => 'Equipo permanente inicial conservado del último reparto registrado.',
                'vigente_desde' => now(), 'vigente_hasta' => null, 'creado_por_id' => $anterior->creado_por_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (DB::table('comisiones_empaque_reparto_participantes')->where('reparto_id', $anterior->id)->get() as $linea) {
                DB::table('comisiones_empaque_reparto_participantes')->insert([
                    'reparto_id' => $id, 'participante_id' => $linea->participante_id, 'puntos_base' => $linea->puntos_base,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comisiones_empaque_periodos');
        // Conservar las versiones y sus referencias históricas al revertir el esquema.
        Schema::table('comisiones_empaque_repartos', function (Blueprint $table) {
            $table->dropIndex(['permanente']);
            $table->dropColumn('permanente');
        });
    }
};
