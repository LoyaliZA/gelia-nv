<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_zonas_pedido', function (Blueprint $table) {
            $table->decimal('costo_adicional', 12, 2)->nullable()->after('nombre');
        });

        DB::table('catalogo_zonas_pedido')
            ->where('nombre', 'like', 'Con reexpedici%')
            ->whereNull('costo_adicional')
            ->update(['costo_adicional' => 150, 'updated_at' => now()]);

        DB::table('catalogo_zonas_pedido')
            ->where('nombre', 'like', 'Sin reexpedici%')
            ->whereNull('costo_adicional')
            ->update(['costo_adicional' => 0, 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('catalogo_zonas_pedido', function (Blueprint $table) {
            $table->dropColumn('costo_adicional');
        });
    }
};
