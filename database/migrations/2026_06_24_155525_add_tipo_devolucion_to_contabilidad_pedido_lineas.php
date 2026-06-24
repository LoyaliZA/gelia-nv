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
        Schema::table('contabilidad_pedido_lineas', function (Blueprint $table) {
            $table->string('tipo_devolucion', 50)->default('normal')->after('subtotal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contabilidad_pedido_lineas', function (Blueprint $table) {
            $table->dropColumn('tipo_devolucion');
        });
    }
};
