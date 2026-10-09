<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            // folio_remision es el número de pedido capturado por Ventas.
            $table->string('numero_remision', 64)->nullable()->after('folio_remision');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropColumn('numero_remision');
        });
    }
};
