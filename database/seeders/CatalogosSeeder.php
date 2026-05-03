<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoProceso;

class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        // Población de Estados de Solicitud
        $estados = [
            ['nombre' => 'Pendiente', 'descripcion' => 'Solicitud recién creada por el vendedor'],
            ['nombre' => 'Respondida', 'descripcion' => 'La encargada de TAGS ha procesado la solicitud'],
            ['nombre' => 'Verificada', 'descripcion' => 'El auxiliar confirmó que la información es correcta'],
            ['nombre' => 'Incorrecta', 'descripcion' => 'Se encontró una inconsistencia y se requiere corrección'],
        ];

        foreach ($estados as $estado) {
            CatalogoEstadoSolicitud::firstOrCreate(['nombre' => $estado['nombre']], $estado);
        }

        // Población de Procesos
        $procesos = [
            ['nombre' => 'CAMBIO DE LISTA'],
            ['nombre' => 'ASIGNAR CLIENTE NUEVO'],
            ['nombre' => 'ASIGNAR CLIENTE REACTIVADO'],
            ['nombre' => 'ASIGNAR CLIENTE REACTIVADO Y CAMBIO DE LISTA'],
            ['nombre' => 'ASIGNAR CLIENTE REACTIVADO-HER Y CAMBIO DE LISTA'],
        ];

        foreach ($procesos as $proceso) {
            CatalogoProceso::firstOrCreate(['nombre' => $proceso['nombre']], $proceso);
        }
    }
}