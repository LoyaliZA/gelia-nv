<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->timestamp('consulta_cerrada_at')->nullable()->after('pesaje_respondido_por_id');
            $table->foreignId('consulta_cerrada_por_id')->nullable()->after('consulta_cerrada_at')
                ->constrained('users')->nullOnDelete();
            $table->boolean('consulta_actualizacion_pendiente')->default(false)->after('consulta_cerrada_por_id');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consulta_cerrada_por_id');
            $table->dropColumn(['consulta_cerrada_at', 'consulta_actualizacion_pendiente']);
        });
    }
};
