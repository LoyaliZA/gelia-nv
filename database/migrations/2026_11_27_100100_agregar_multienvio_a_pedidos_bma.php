<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->foreignId('multienvio_nacional_id')->nullable()->index('pedidos_bma_multienvio_nacional_idx')->constrained('multienvios_nacionales');
            $table->unsignedTinyInteger('multienvio_orden')->nullable();
            $table->unique(['multienvio_nacional_id', 'multienvio_orden'], 'pedidos_bma_multienvio_orden_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('pedidos_bma')->whereNotNull('multienvio_nacional_id')->exists()) {
            throw new RuntimeException('No se puede revertir: hay pedidos asignados a multienvíos nacionales.');
        }

        Schema::table('pedidos_bma', function (Blueprint $table) {
            // Primero la FK, luego los índices que la respaldan (MySQL error 1553 si se invierte).
            $table->dropForeign(['multienvio_nacional_id']);
            $table->dropUnique('pedidos_bma_multienvio_orden_unique');
            $table->dropIndex('pedidos_bma_multienvio_nacional_idx');
            $table->dropColumn(['multienvio_nacional_id', 'multienvio_orden']);
        });
    }
};
