<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->foreignId('pedido_principal_id')
                ->nullable()
                ->after('tipo_operacion_envio_id')
                ->constrained('pedidos_bma')
                ->restrictOnDelete();
            $table->index('pedido_principal_id');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pedido_principal_id');
        });
    }
};
