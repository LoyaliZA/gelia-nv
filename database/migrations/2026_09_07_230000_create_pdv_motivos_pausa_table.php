<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_motivos_pausa', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('nombre', 120);
            $table->boolean('activo')->default(true);
            $table->boolean('requiere_detalle')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['activo', 'orden'], 'pdv_motivos_pausa_activo_orden_idx');
        });

        $ahora = now();

        DB::table('pdv_motivos_pausa')->insert([
            [
                'slug' => 'comida',
                'nombre' => 'Comida',
                'activo' => true,
                'requiere_detalle' => false,
                'orden' => 10,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'slug' => 'bano',
                'nombre' => 'Baño',
                'activo' => true,
                'requiere_detalle' => false,
                'orden' => 20,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'slug' => 'capacitacion',
                'nombre' => 'Capacitación',
                'activo' => true,
                'requiere_detalle' => false,
                'orden' => 30,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'slug' => 'apoyo_otra_area',
                'nombre' => 'Apoyo en otra área',
                'activo' => true,
                'requiere_detalle' => false,
                'orden' => 40,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'slug' => 'incidencia_sistema',
                'nombre' => 'Incidencia del sistema',
                'activo' => true,
                'requiere_detalle' => false,
                'orden' => 50,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'slug' => 'encargo_gerencia',
                'nombre' => 'Encargo de gerencia',
                'activo' => true,
                'requiere_detalle' => false,
                'orden' => 60,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'slug' => 'otro',
                'nombre' => 'Otro',
                'activo' => true,
                'requiere_detalle' => true,
                'orden' => 70,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_motivos_pausa');
    }
};
