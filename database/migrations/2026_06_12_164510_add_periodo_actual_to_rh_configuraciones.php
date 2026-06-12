<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rh_configuraciones', function (Blueprint $table) {
            $table->date('periodo_actual_inicio')->nullable()->after('dias_periodo_pago');
            $table->date('periodo_actual_fin')->nullable()->after('periodo_actual_inicio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rh_configuraciones', function (Blueprint $table) {
            $table->dropColumn(['periodo_actual_inicio', 'periodo_actual_fin']);
        });
    }
};
