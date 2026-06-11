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
        $sexos = ['Hombre', 'Mujer', 'Otro'];
        foreach ($sexos as $sexo) {
            DB::table('catalogo_sexos')->updateOrInsert(['nombre' => $sexo]);
        }

        $estados = [
            ['nombre' => 'Pendiente', 'descripcion' => 'Solicitud recién creada por el vendedor'],
            ['nombre' => 'Respondida', 'descripcion' => 'La encargada de TAGS ha procesado la solicitud'],
            ['nombre' => 'Verificada', 'descripcion' => 'El auxiliar confirmó que la información es correcta'],
            ['nombre' => 'Incorrecta', 'descripcion' => 'Se encontró una inconsistencia y se requiere corrección'],
            ['nombre' => 'Cancelada', 'descripcion' => 'Solicitud anulada con reversión de cambios si aplica'],
        ];

        foreach ($estados as $estado) {
            CatalogoEstadoSolicitud::updateOrCreate(['nombre' => $estado['nombre']], $estado);
        }

        $procesos = [
            ['nombre' => 'CAMBIO DE LISTA'],
            ['nombre' => 'ASIGNAR CLIENTE NUEVO'],
            ['nombre' => 'ASIGNAR CLIENTE REACTIVADO'],
            ['nombre' => 'ASIGNAR CLIENTE REACTIVADO Y CAMBIO DE LISTA'],
            ['nombre' => 'ASIGNAR CLIENTE REACTIVADO-HER Y CAMBIO DE LISTA'],
        ];

        foreach ($procesos as $proceso) {
            CatalogoProceso::updateOrCreate(
                ['nombre' => $proceso['nombre']],
                array_merge(['categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO], $proceso)
            );
        }

        $procesosOperativos = [
            ['nombre' => 'CANCELACIÓN DE REMISIÓN'],
            ['nombre' => 'CANCELACIÓN DE PEDIDO'],
            ['nombre' => 'SOLICITAR COTIZACION SOBRE PEDIDO CANCELADO'],
        ];

        foreach ($procesosOperativos as $proceso) {
            CatalogoProceso::updateOrCreate(
                ['nombre' => $proceso['nombre']],
                array_merge(['categoria_flujo' => CatalogoProceso::CATEGORIA_OPERATIVO], $proceso)
            );
        }
    }
}
