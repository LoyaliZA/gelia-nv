<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('importaciones_almacen_logs', function (Blueprint $table) {
            $table->json('operaciones')->nullable()->after('mapping');
            $table->string('archivo_hash', 64)->nullable()->after('archivo_normalizado');
            $table->unsignedInteger('productos_creados')->default(0)->after('omitidos');
            $table->unsignedInteger('productos_actualizados')->default(0)->after('productos_creados');
            $table->unsignedInteger('asignaciones_creadas')->default(0)->after('productos_actualizados');
            $table->unsignedInteger('costos_creados')->default(0)->after('asignaciones_creadas');
            $table->unsignedInteger('costos_actualizados')->default(0)->after('costos_creados');
            $table->unsignedInteger('cantidades_actualizadas')->default(0)->after('costos_actualizados');
            $table->unsignedInteger('sin_cambios')->default(0)->after('cantidades_actualizadas');
            $table->json('resumen_simulacion')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('importaciones_almacen_logs', function (Blueprint $table) {
            $table->dropColumn([
                'operaciones',
                'archivo_hash',
                'productos_creados',
                'productos_actualizados',
                'asignaciones_creadas',
                'costos_creados',
                'costos_actualizados',
                'cantidades_actualizadas',
                'sin_cambios',
                'resumen_simulacion',
            ]);
        });
    }
};
