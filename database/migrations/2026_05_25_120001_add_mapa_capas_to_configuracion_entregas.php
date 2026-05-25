<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_entregas', function (Blueprint $table) {
            $table->boolean('mostrar_zonas_principales')->default(true)->after('google_map_id');
            $table->boolean('mostrar_zonas_restringidas')->default(true)->after('mostrar_zonas_principales');
            $table->boolean('mostrar_zonas_periferia')->default(false)->after('mostrar_zonas_restringidas');
            $table->boolean('mostrar_radio_tolerancia')->default(true)->after('mostrar_zonas_periferia');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_entregas', function (Blueprint $table) {
            $table->dropColumn([
                'mostrar_zonas_principales',
                'mostrar_zonas_restringidas',
                'mostrar_zonas_periferia',
                'mostrar_radio_tolerancia',
            ]);
        });
    }
};
