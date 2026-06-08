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
        Schema::table('cobranza_alertas', function (Blueprint $table) {
            $table->string('tipo')->default('vencimiento')->after('factura_id');
            $table->foreignId('factura_id')->nullable()->change();
            $table->integer('dias_atraso')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cobranza_alertas', function (Blueprint $table) {
            $table->dropColumn('tipo');
            $table->foreignId('factura_id')->nullable(false)->change();
            $table->integer('dias_atraso')->nullable(false)->change();
        });
    }
};
