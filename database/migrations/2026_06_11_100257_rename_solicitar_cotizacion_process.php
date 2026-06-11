<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('catalogo_procesos')
            ->where('nombre', 'SOLICITAR COTIZACIÓN SOBRE PEDIDO')
            ->update(['nombre' => 'SOLICITAR COTIZACION SOBRE PEDIDO CANCELADO']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('catalogo_procesos')
            ->where('nombre', 'SOLICITAR COTIZACION SOBRE PEDIDO CANCELADO')
            ->update(['nombre' => 'SOLICITAR COTIZACIÓN SOBRE PEDIDO']);
    }
};

