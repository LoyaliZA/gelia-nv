<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_bma_bultos_empaque', fn (Blueprint $table) => $table->string('tipo_bulto', 8)->nullable());
        Schema::table('pedido_bma_cumplimiento_fisico', function (Blueprint $table) {
            $table->unsignedInteger('cajas_empaque')->nullable();
            $table->unsignedInteger('bolsas_empaque')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pedido_bma_bultos_empaque', fn (Blueprint $table) => $table->dropColumn('tipo_bulto'));
        Schema::table('pedido_bma_cumplimiento_fisico', fn (Blueprint $table) => $table->dropColumn(['cajas_empaque', 'bolsas_empaque']));
    }
};
