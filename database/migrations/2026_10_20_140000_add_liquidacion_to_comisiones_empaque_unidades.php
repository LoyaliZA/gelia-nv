<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comisiones_empaque_unidades', function (Blueprint $table) {
            $table->string('liquidacion', 20)->default('pendiente')->after('situacion');
            $table->index(['area_id', 'folio_remision', 'liquidacion'], 'ce_unidades_remision_liq_idx');
        });
    }

    public function down(): void
    {
        Schema::table('comisiones_empaque_unidades', function (Blueprint $table) {
            $table->dropIndex('ce_unidades_remision_liq_idx');
            $table->dropColumn('liquidacion');
        });
    }
};
