<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoProceso;
use Illuminate\Support\Facades\DB;

class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        // Población de Sexos (Necesario para el perfil de usuario)
        $sexos = ['Hombre', 'Mujer', 'Otro'];
        foreach ($sexos as $sexo) {
            DB::table('catalogo_sexos')->updateOrInsert(['nombre' => $sexo]);
        }

        // Población de Estados de Solicitud (Basado en requerimientos actuales)
        $estados = [
            ['nombre' => 'Pendiente', 'descripcion' => 'Solicitud recién creada por el vendedor'],
            ['nombre' => 'Respondida', 'descripcion' => 'La encargada de TAGS ha procesado la solicitud'],
            ['nombre' => 'Verificada', 'descripcion' => 'El auxiliar confirmó que la información es correcta'],
            ['nombre' => 'Incorrecta', 'descripcion' => 'Se encontró una inconsistencia y se requiere corrección'],
        ];

        foreach ($estados as $estado) {
            CatalogoEstadoSolicitud::updateOrCreate(['nombre' => $estado['nombre']], $estado);
        }

        // Población de Procesos (Consolidado)
        $procesos = [
            ['nombre' => 'CAMBIO DE LISTA'],
            ['nombre' => 'ASIGNAR CLIENTE NUEVO'],
            ['nombre' => 'ASIGNAR CLIENTE REACTIVADO'],
            ['nombre' => 'ASIGNAR CLIENTE REACTIVADO Y CAMBIO DE LISTA'],
            ['nombre' => 'ASIGNAR CLIENTE REACTIVADO-HER Y CAMBIO DE LISTA'],
        ];

        foreach ($procesos as $proceso) {
            CatalogoProceso::updateOrCreate(['nombre' => $proceso['nombre']], $proceso);
        }
    }
}