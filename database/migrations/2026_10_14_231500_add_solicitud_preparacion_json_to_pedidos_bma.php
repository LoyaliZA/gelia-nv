<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intención de preparación del borrador. No sustituye la tarea ni sus snapshots.
 * Registros anteriores quedan con NULL y se leen sin inventar datos municipales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos_bma', 'solicitud_preparacion_json')) {
                $table->json('solicitud_preparacion_json')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos_bma', 'solicitud_preparacion_json')) {
                $table->dropColumn('solicitud_preparacion_json');
            }
        });
    }
};
