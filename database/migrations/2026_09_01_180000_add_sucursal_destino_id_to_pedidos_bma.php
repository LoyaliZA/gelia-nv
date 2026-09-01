<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->foreignId('sucursal_destino_id')
                ->nullable()
                ->after('almacen_id')
                ->constrained('sucursales')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sucursal_destino_id');
        });
    }
};
