<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_paqueterias_pedido', function (Blueprint $table) {
            $table->boolean('permite_costo_diferido')->default(false)->after('categoria');
        });

        DB::table('catalogo_paqueterias_pedido')
            ->where('categoria', 'local_regional')
            ->update(['permite_costo_diferido' => true]);

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->unsignedInteger('cantidad_piezas')->nullable()->after('numero_cajas');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropColumn('cantidad_piezas');
        });

        Schema::table('catalogo_paqueterias_pedido', function (Blueprint $table) {
            $table->dropColumn('permite_costo_diferido');
        });
    }
};
