<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\CatalogoZonaEntrega;

class ZonasEntregaSeeder extends Seeder
{
    // ----------------------------------------------------------------------
    // EJECUCIÓN DEL SEEDER
    // ----------------------------------------------------------------------
    public function run(): void
    {
        // 1. Definición de ruta absoluta al archivo
        $rutaArchivo = storage_path('app/villahermosa.json');
        
        if (!File::exists($rutaArchivo)) {
            $this->command->error('No se encontró el archivo exactamente en: ' . $rutaArchivo);
            return;
        }

        // 2. Lectura del archivo mediante Facade File
        $json = File::get($rutaArchivo);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('El archivo no tiene un formato JSON válido.');
            return;
        }

        // 3. Configuración de colores visuales
        $colores = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444'];
        $indexColor = 0;

        // 4. Inserción de Zonas (Iteración de FeatureCollection)
        foreach ($data['features'] as $feature) {
            $nombreZona = array_key_first($feature['properties']); 
            
            CatalogoZonaEntrega::create([
                'nombre' => $nombreZona,
                'coordenadas_poligono' => $feature['geometry'],
                'color_hex' => $colores[$indexColor % count($colores)],
                'costo_base' => 50.00,
                'activo' => true
            ]);

            $indexColor++;
        }

        $this->command->info('Zonas de entrega importadas correctamente.');
    }
}