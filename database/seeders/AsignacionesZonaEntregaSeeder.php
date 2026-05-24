<?php

namespace Database\Seeders;

use App\Models\CatalogoZonaEntrega;
use App\Models\CatalogoZonaEntregaOverride;
use Illuminate\Database\Seeder;

class AsignacionesZonaEntregaSeeder extends Seeder
{
    /**
     * Asignaciones especiales dentro de polígonos más amplios (ej. Ciudad Industrial → horario ZONA 3).
     */
    public function run(): void
    {
        $zonaTres = CatalogoZonaEntrega::where('nombre', 'ZONA 3')->first();

        if (!$zonaTres) {
            $this->command->warn('No se encontró ZONA 3. Ejecuta ZonasEntregaSeeder primero.');
            return;
        }

        CatalogoZonaEntregaOverride::updateOrCreate(
            ['nombre' => 'Ciudad Industrial'],
            [
                'zona_referencia_id' => $zonaTres->id,
                'activo' => true,
                'prioridad' => 1,
                'coordenadas_poligono' => [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [-92.9670, 17.9860],
                        [-92.9525, 17.9860],
                        [-92.9525, 17.9940],
                        [-92.9670, 17.9940],
                        [-92.9670, 17.9860],
                    ]],
                ],
            ]
        );

        $this->command->info('Asignaciones especiales de zonas sincronizadas.');
    }
}
