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
        Schema::table('cobranza_facturas', function (Blueprint $table) {
            $table->boolean('verificado_manualmente')->default(false)->after('pagada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cobranza_facturas', function (Blueprint $table) {
            $table->dropColumn('verificado_manualmente');
        });
    }
};
