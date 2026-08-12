<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'INCIDENCIA_CEDIS')
            ->update([
                'nombre_visual' => 'Error CEDIS',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'INCIDENCIA_CEDIS')
            ->update([
                'nombre_visual' => 'Incidencia CEDIS',
                'updated_at' => now(),
            ]);
    }
};
