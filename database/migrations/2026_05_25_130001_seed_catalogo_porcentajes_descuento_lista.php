<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('catalogo_porcentajes_escalonamiento_lista')) {
            return;
        }

        $defaultsEscalonamiento = [
            'BRONCE'   => 0.00,
            'PLATA'    => 2.00,
            'ORO'      => 4.00,
            'DIAMANTE' => 6.00,
        ];

        $listas = DB::table('catalogo_listas_descuento')->get(['id', 'nombre']);

        foreach ($listas as $lista) {
            $nombreUpper = strtoupper($lista->nombre);
            $porcentaje = 0.0;

            if (str_contains($nombreUpper, 'COLABORADOR') || str_contains($nombreUpper, 'PLATAFORMAS')) {
                continue;
            }

            if (!str_contains($nombreUpper, 'PUBLICO') && !str_contains($nombreUpper, 'PÚBLICO')) {
                foreach ($defaultsEscalonamiento as $keyword => $pct) {
                    if (str_contains($nombreUpper, $keyword)) {
                        $porcentaje = $pct;
                        break;
                    }
                }
            }

            DB::table('catalogo_porcentajes_escalonamiento_lista')->updateOrInsert(
                ['catalogo_lista_descuento_id' => $lista->id],
                [
                    'porcentaje_descuento' => $porcentaje,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('catalogo_porcentajes_escalonamiento_lista')->truncate();
    }
};
