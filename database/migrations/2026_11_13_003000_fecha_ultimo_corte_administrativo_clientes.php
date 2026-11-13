<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clientes') && ! Schema::hasColumn('clientes', 'fecha_ultimo_corte_administrativo')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dateTime('fecha_ultimo_corte_administrativo')->nullable()->after('monto_venta_actual');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clientes') && Schema::hasColumn('clientes', 'fecha_ultimo_corte_administrativo')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropColumn('fecha_ultimo_corte_administrativo');
            });
        }
    }
};
