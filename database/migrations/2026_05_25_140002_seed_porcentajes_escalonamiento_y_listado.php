<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tablaEscalonamiento = Schema::hasTable('catalogo_porcentajes_escalonamiento_lista')
            ? 'catalogo_porcentajes_escalonamiento_lista'
            : (Schema::hasTable('catalogo_porcentajes_descuento_lista')
                ? 'catalogo_porcentajes_descuento_lista'
                : null);

        if (!$tablaEscalonamiento) {
            return;
        }

        $settings = Schema::hasTable('gelia_settings')
            ? DB::table('gelia_settings')->pluck('value', 'key')
            : collect();

        // Escalonamiento: descuento simple sobre cotización (no confundir con listados)
        $defaultsEscalonamiento = [
            'BRONCE'   => 0.00,
            'PLATA'    => 2.00,
            'ORO'      => 4.00,
            'DIAMANTE' => 6.00,
        ];

        // Listados: porcentajes para generación de listas de resurtido
        $defaultsListado = [
            'BRONCE'   => (float) ($settings['pct_bronce'] ?? 12.39),
            'PLATA'    => (float) ($settings['pct_plata'] ?? 14.14),
            'ORO'      => (float) ($settings['pct_oro'] ?? 15.89),
            'DIAMANTE' => (float) ($settings['pct_diamante'] ?? 17.65),
        ];

        $listas = DB::table('catalogo_listas_descuento')->get(['id', 'nombre']);

        foreach ($listas as $lista) {
            $nombreUpper = strtoupper($lista->nombre);

            if (str_contains($nombreUpper, 'COLABORADOR') || str_contains($nombreUpper, 'PLATAFORMAS')) {
                continue;
            }

            $pctEscalonamiento = 0.0;
            $pctListado = 0.0;

            if (str_contains($nombreUpper, 'PUBLICO') || str_contains($nombreUpper, 'PÚBLICO')) {
                $pctEscalonamiento = 0.0;
                $pctListado = 0.0;
            } else {
                foreach ($defaultsEscalonamiento as $keyword => $pct) {
                    if (str_contains($nombreUpper, $keyword)) {
                        $pctEscalonamiento = $pct;
                        break;
                    }
                }
                foreach ($defaultsListado as $keyword => $pct) {
                    if (str_contains($nombreUpper, $keyword)) {
                        $pctListado = $pct;
                        break;
                    }
                }
            }

            DB::table($tablaEscalonamiento)->updateOrInsert(
                ['catalogo_lista_descuento_id' => $lista->id],
                [
                    'porcentaje_descuento' => $pctEscalonamiento,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            if (Schema::hasTable('catalogo_porcentajes_listado_lista')) {
                DB::table('catalogo_porcentajes_listado_lista')->updateOrInsert(
                    ['catalogo_lista_descuento_id' => $lista->id],
                    [
                        'porcentaje_descuento' => $pctListado,
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        // Datos de referencia; no revertir para evitar pérdida de configuración manual.
    }
};
