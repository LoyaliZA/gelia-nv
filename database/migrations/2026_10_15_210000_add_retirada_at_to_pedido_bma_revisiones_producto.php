<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_bma_revisiones_producto', function (Blueprint $table) {
            $table->timestamp('retirada_at')->nullable()->after('resolucion_at');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_bma_revisiones_producto', function (Blueprint $table) {
            $table->dropColumn('retirada_at');
        });
    }
};
