<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reporte_pagos_pedidos_exportaciones', function (Blueprint $table) {
            $table->string('tipo_reporte', 20)->default('pedido')->after('formato');
        });
    }

    public function down(): void
    {
        Schema::table('reporte_pagos_pedidos_exportaciones', function (Blueprint $table) {
            $table->dropColumn('tipo_reporte');
        });
    }
};
