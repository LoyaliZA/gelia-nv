<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_almacen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->cascadeOnDelete();
            $table->string('ubicacion', 50)->nullable();
            $table->boolean('activo_en_almacen')->default(true);
            $table->string('origen_asignacion', 32)->default('manual');
            $table->timestamps();

            $table->unique(['producto_id', 'almacen_id']);
        });

        $filas = DB::table('inventarios')->select('producto_id', 'almacen_id', 'ubicacion', 'created_at', 'updated_at')->get();
        foreach ($filas as $fila) {
            DB::table('producto_almacen')->insertOrIgnore([
                'producto_id' => $fila->producto_id,
                'almacen_id' => $fila->almacen_id,
                'ubicacion' => $fila->ubicacion,
                'activo_en_almacen' => true,
                'origen_asignacion' => 'migracion_inventarios',
                'created_at' => $fila->created_at ?? now(),
                'updated_at' => $fila->updated_at ?? now(),
            ]);
        }

        $costos = DB::table('producto_costos')->select('producto_id', 'almacen_id', 'created_at', 'updated_at')->get();
        foreach ($costos as $fila) {
            DB::table('producto_almacen')->insertOrIgnore([
                'producto_id' => $fila->producto_id,
                'almacen_id' => $fila->almacen_id,
                'ubicacion' => null,
                'activo_en_almacen' => true,
                'origen_asignacion' => 'migracion_costos',
                'created_at' => $fila->created_at ?? now(),
                'updated_at' => $fila->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_almacen');
    }
};
