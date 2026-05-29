<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Elimina el polígono inventado de Ciudad Industrial (seeder con coordenadas hardcodeadas).
     */
    public function up(): void
    {
        DB::table('catalogo_zona_entrega_overrides')
            ->where('nombre', 'Ciudad Industrial')
            ->delete();
    }

    public function down(): void
    {
        // No se restaura el polígono incorrecto.
    }
};
