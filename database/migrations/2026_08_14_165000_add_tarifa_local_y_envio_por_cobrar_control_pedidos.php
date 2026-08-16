<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_paqueterias_pedido', function (Blueprint $table) {
            $table->string('modalidad_tarifa', 20)->nullable()->after('permite_costo_diferido');
            $table->decimal('tarifa_monto', 12, 2)->nullable()->after('modalidad_tarifa');
            $table->string('tarifa_unidad_peso', 5)->nullable()->after('tarifa_monto');
            $table->decimal('tarifa_paso_peso', 12, 4)->nullable()->after('tarifa_unidad_peso');
        });

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->boolean('envio_por_cobrar')->default(false)->after('cliente_proporciona_guia');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropColumn('envio_por_cobrar');
        });

        Schema::table('catalogo_paqueterias_pedido', function (Blueprint $table) {
            $table->dropColumn([
                'modalidad_tarifa',
                'tarifa_monto',
                'tarifa_unidad_peso',
                'tarifa_paso_peso',
            ]);
        });
    }
};
