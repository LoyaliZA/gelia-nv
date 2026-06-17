<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SoporteCatalogoEstado;
use App\Models\SoporteCatalogoPrioridad;
use App\Models\SoporteCatalogoCategoria;
use App\Models\SoporteCatalogoModulo;
use App\Models\SoporteConfiguracion;

class SoporteSeeder extends Seeder
{
    public function run(): void
    {
        // Estados
        $estados = [
            ['nombre' => 'Abierto', 'color' => '#3b82f6'],
            ['nombre' => 'Asignado', 'color' => '#8b5cf6'],
            ['nombre' => 'En Progreso', 'color' => '#f59e0b'],
            ['nombre' => 'Esperando Usuario', 'color' => '#64748b'],
            ['nombre' => 'Resuelto', 'color' => '#10b981'],
            ['nombre' => 'Cerrado', 'color' => '#0f172a'],
        ];
        foreach ($estados as $estado) {
            SoporteCatalogoEstado::updateOrCreate(['nombre' => $estado['nombre']], $estado);
        }

        // Prioridades
        $prioridades = [
            ['nombre' => 'Baja', 'tiempo_respuesta_horas' => 48],
            ['nombre' => 'Media', 'tiempo_respuesta_horas' => 24],
            ['nombre' => 'Alta', 'tiempo_respuesta_horas' => 8],
            ['nombre' => 'Crítica', 'tiempo_respuesta_horas' => 4],
        ];
        foreach ($prioridades as $prioridad) {
            SoporteCatalogoPrioridad::updateOrCreate(['nombre' => $prioridad['nombre']], $prioridad);
        }

        // Categorías
        $categorias = [
            ['nombre' => 'Error de Sistema'],
            ['nombre' => 'Duda de Uso'],
            ['nombre' => 'Solicitud de Mejora'],
            ['nombre' => 'Problema de Acceso'],
        ];
        foreach ($categorias as $categoria) {
            SoporteCatalogoCategoria::updateOrCreate(['nombre' => $categoria['nombre']], $categoria);
        }

        // Módulos iniciales sugeridos
        $modulos = [
            ['nombre' => 'Solicitudes', 'permiso_requerido' => 'solicitudes.ver_listado'],
            ['nombre' => 'Clientes', 'permiso_requerido' => 'clientes.ver'],
            ['nombre' => 'Usuarios y Permisos', 'permiso_requerido' => 'usuarios.gestionar'],
            ['nombre' => 'Catálogos y Comisiones', 'permiso_requerido' => 'catalogos.comisiones.ver'],
            ['nombre' => 'Configuración del Sistema', 'permiso_requerido' => 'configuracion_sistema.gestionar'],
        ];
        foreach ($modulos as $modulo) {
            SoporteCatalogoModulo::updateOrCreate(['nombre' => $modulo['nombre']], $modulo);
        }

        // Configuración Inicial
        if (SoporteConfiguracion::count() == 0) {
            SoporteConfiguracion::create([
                'horario_inicio' => '09:00:00',
                'horario_fin' => '17:00:00',
                'mensaje_fuera_horario' => 'Estás levantando un ticket fuera del horario laboral. Se responderá tan pronto sea posible.',
                'hora_notificacion_diaria' => '09:30:00',
            ]);
        }
    }
}
