<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Departamento;
use App\Models\Area;

class OrganizacionSeeder extends Seeder
{
    public function run(): void
    {
        // Creamos los Departamentos base
        $deptos = [
            'TI' => ['Soporte', 'Desarrollo', 'Infraestructura'],
            'Cedis' => ['Logística', 'Almacén', 'Embarques'],
            'Bellaroma' => ['Ventas', 'Atención a Clientes', 'Marketing'],
            'Aromas' => ['Producción', 'Calidad']
        ];

        foreach ($deptos as $deptoNombre => $areas) {
            $depto = Departamento::create(['nombre' => $deptoNombre, 'activo' => true]);
            foreach ($areas as $areaNombre) {
                Area::create([
                    'nombre' => $areaNombre,
                    'departamento_id' => $depto->id
                ]);
            }
        }
    }
}