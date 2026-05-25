<?php

namespace Database\Seeders;

use App\Models\CatalogoZonaEntrega;
use App\Models\CatalogoZonaPeriferia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ZonasPeriferiaSeeder extends Seeder
{
    public function run(): void
    {
        $rutaArchivo = database_path('data/zonas_extendidas_periferia.json');

        if (!File::exists($rutaArchivo)) {
            $this->command->error('No se encontró el archivo JSON en: ' . $rutaArchivo);
            return;
        }

        $json = File::get($rutaArchivo);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('El archivo no tiene un formato JSON válido.');
            return;
        }

        foreach ($data['features'] as $feature) {
            $nombrePeriferia = array_key_first($feature['properties']);

            if (!preg_match('/PERIFERIA ZONA (\d+)/', $nombrePeriferia, $matches)) {
                $this->command->warn("Nombre de periferia no reconocido: {$nombrePeriferia}");
                continue;
            }

            $zonaReferencia = CatalogoZonaEntrega::where('nombre', 'ZONA ' . $matches[1])->first();

            if (!$zonaReferencia) {
                $this->command->warn("No se encontró ZONA {$matches[1]} para {$nombrePeriferia}. Ejecuta ZonasEntregaSeeder primero.");
                continue;
            }

            CatalogoZonaPeriferia::updateOrCreate(
                ['nombre' => $nombrePeriferia],
                [
                    'coordenadas_poligono' => $feature['geometry'],
                    'zona_referencia_id' => $zonaReferencia->id,
                    'activo' => true,
                ]
            );
        }

        $this->command->info('Zonas de periferia extendida importadas correctamente.');
    }
}
