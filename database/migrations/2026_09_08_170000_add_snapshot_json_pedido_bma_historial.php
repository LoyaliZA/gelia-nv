<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_bma_historial_estados', function (Blueprint $table) {
            $table->json('snapshot_json')->nullable()->after('evidencia_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_bma_historial_estados', function (Blueprint $table) {
            $table->dropColumn('snapshot_json');
        });
    }
};
