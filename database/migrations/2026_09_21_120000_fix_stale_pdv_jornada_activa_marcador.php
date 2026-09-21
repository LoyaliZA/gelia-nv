<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pdv_jornadas')
            ->where('estado', 'CERRADA')
            ->whereNotNull('jornada_activa_marcador')
            ->update(['jornada_activa_marcador' => null]);

        DB::table('pdv_intervalos_operativos')
            ->whereNotNull('fin_at')
            ->whereNotNull('intervalo_abierto_marcador')
            ->update(['intervalo_abierto_marcador' => null]);
    }

    public function down(): void
    {
        // No reversible: el marcador obsoleto no debe restaurarse.
    }
};
