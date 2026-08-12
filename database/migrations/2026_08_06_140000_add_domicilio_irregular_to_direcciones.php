<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente_direcciones', function (Blueprint $table) {
            $table->boolean('domicilio_irregular')->default(false)->after('indicaciones_entrega');
        });

        Schema::table('pedido_bma_direcciones', function (Blueprint $table) {
            $table->boolean('domicilio_irregular')->default(false)->after('indicaciones_entrega');
        });
    }

    public function down(): void
    {
        Schema::table('cliente_direcciones', function (Blueprint $table) {
            $table->dropColumn('domicilio_irregular');
        });

        Schema::table('pedido_bma_direcciones', function (Blueprint $table) {
            $table->dropColumn('domicilio_irregular');
        });
    }
};
