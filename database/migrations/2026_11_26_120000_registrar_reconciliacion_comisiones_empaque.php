<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comisiones_empaque_configuracion', function (Blueprint $table) {
            $table->unsignedBigInteger('version_rango')->default(0);
            $table->unsignedBigInteger('version_reconciliada')->default(0);
        });
        Schema::table('comisiones_empaque_sincronizaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('version_rango')->nullable();
        });

        DB::table('comisiones_empaque_configuracion')->whereNotNull('fecha_inicio')->update(['version_rango' => 1]);
    }

    public function down(): void
    {
        Schema::table('comisiones_empaque_sincronizaciones', fn (Blueprint $table) => $table->dropColumn('version_rango'));
        Schema::table('comisiones_empaque_configuracion', fn (Blueprint $table) => $table->dropColumn(['version_rango', 'version_reconciliada']));
    }
};
