<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comisiones_empaque_configuracion', function (Blueprint $table) {
            $table->date('fecha_fin')->nullable()->after('fecha_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('comisiones_empaque_configuracion', function (Blueprint $table) {
            $table->dropColumn('fecha_fin');
        });
    }
};
