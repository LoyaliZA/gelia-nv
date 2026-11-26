<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comisiones_empaque_unidades', function (Blueprint $table) {
            $table->json('conciliacion')->nullable();
        });
        Schema::table('comisiones_empaque_reparto_participantes', function (Blueprint $table) {
            $table->boolean('encargado')->default(false);
        });
        Schema::table('comisiones_empaque_sincronizaciones', function (Blueprint $table) {
            $table->json('area_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('comisiones_empaque_unidades', function (Blueprint $table) {
            $table->dropColumn('conciliacion');
        });
        Schema::table('comisiones_empaque_reparto_participantes', function (Blueprint $table) {
            $table->dropColumn('encargado');
        });
        Schema::table('comisiones_empaque_sincronizaciones', function (Blueprint $table) {
            $table->dropColumn('area_ids');
        });
    }
};
