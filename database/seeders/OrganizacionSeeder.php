<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Departamento;
use App\Models\Area;

class OrganizacionSeeder extends Seeder
{
    public function run(): void
    {
        $estructura = [
            'TI' => ['Soporte', 'Desarrollo', 'Infraestructura', 'Auxiliar'],
            'Cedis' => ['Logística', 'Almacén', 'Embarques', 'Auxiliar'],
            'Bellaroma' => ['Ventas', 'Atención a Clientes', 'Marketing', 'Auxiliar'],
            'Aromas' => ['Administración', 'Contabilidad', 'Facturación', 'Recursos Humanos', 'Call Center', 'Punto de Venta', 'Auxiliar']
        ];

        foreach ($estructura as $deptoNombre => $areas) {
            $depto = Departamento::updateOrCreate(['nombre' => $deptoNombre], ['activo' => true]);
            
            foreach ($areas as $areaNombre) {
                Area::updateOrCreate([
                    'nombre' => $areaNombre,
                    'departamento_id' => $depto->id
                ]);
            }
        }
    }
}