<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_bma_revisiones_producto', function (Blueprint $table) {
            $table->unsignedInteger('cantidad_solicitada')->nullable();
            $table->unsignedInteger('cantidad_encontrada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pedido_bma_revisiones_producto', function (Blueprint $table) {
            $table->dropColumn(['cantidad_solicitada', 'cantidad_encontrada']);
        });
    }
};
