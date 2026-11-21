<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La carátula municipal nace del pedido, sin tarea de preparación. Se conserva la llave foránea.
     * El índice único (tarea, versión) no limita filas con tarea nula; la versión por pedido la
     * asegura el servicio bajo bloqueo del pedido.
     */
    public function up(): void
    {
        Schema::table('pedido_bma_caratulas', function (Blueprint $table) {
            $table->unsignedBigInteger('pedido_bma_tarea_preparacion_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('pedido_bma_caratulas')->whereNull('pedido_bma_tarea_preparacion_id')->exists()) {
            throw new RuntimeException('No se puede revertir: existen carátulas generadas desde el pedido, sin tarea de preparación.');
        }

        Schema::table('pedido_bma_caratulas', function (Blueprint $table) {
            $table->unsignedBigInteger('pedido_bma_tarea_preparacion_id')->nullable(false)->change();
        });
    }
};
