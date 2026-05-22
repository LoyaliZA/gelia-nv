<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\CatalogoZonaRestringida;

class ZonasRestringidasSeeder extends Seeder
{
    // ----------------------------------------------------------------------
    // EJECUCIÓN DEL SEEDER
    // ----------------------------------------------------------------------
    public function run(): void
    {
        // 1. Apuntamos a la carpeta versionada por Git
        $rutaArchivo = database_path('data/zonas_prohibidas.json');
        
        if (!File::exists($rutaArchivo)) {
            $this->command->error('No se encontró el archivo JSON en: ' . $rutaArchivo);
            return;
        }

        // 2. Lectura y decodificación
        $json = File::get($rutaArchivo);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('El archivo no tiene un formato JSON válido.');
            return;
        }

        // 3. Inserción de Zonas Prohibidas
        foreach ($data['features'] as $feature) {
            $nombreZona = array_key_first($feature['properties']); 
            
            CatalogoZonaRestringida::create([
                'nombre' => $nombreZona,
                'coordenadas_poligono' => $feature['geometry'],
                'activo' => true
            ]);
        }

        $this->command->info('Zonas restringidas importadas correctamente.');
    }
}