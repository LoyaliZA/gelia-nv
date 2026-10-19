<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_bma_tarea_productos', function (Blueprint $table) {
            $table->boolean('unica_pieza')->default(false)->after('observacion');
            $table->boolean('mejor_ejemplar')->default(false)->after('unica_pieza');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_bma_tarea_productos', function (Blueprint $table) {
            $table->dropColumn(['unica_pieza', 'mejor_ejemplar']);
        });
    }
};
