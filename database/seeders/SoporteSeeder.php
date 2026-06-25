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

        // Mapeo dinámico de todos los permisos del sistema a módulos de soporte
        $permisos = \Spatie\Permission\Models\Permission::all();

        foreach ($permisos as $permiso) {
            $permisoName = $permiso->name;
            
            $partes = explode('.', $permisoName);
            if (count($partes) < 2) {
                $grupoRaw = 'Sistema';
                $accionRaw = $permisoName;
            } else {
                $accionRaw = end($partes);
                $grupoRaw = implode('.', array_slice($partes, 0, -1));
            }

            // Traducir y formatear el grupo
            $gruposTraducidos = [
                'activos' => 'Activos',
                'solicitudes' => 'Solicitudes',
                'clientes' => 'Clientes',
                'mis_clientes' => 'Mis Clientes',
                'configuracion' => 'Configuración',
                'usuarios' => 'Usuarios',
                'catalogos.comisiones' => 'Catálogos y Comisiones',
                'configuracion_sistema' => 'Configuración del Sistema',
                'soporte' => 'Soporte',
                'mensajeria' => 'Mensajería'
            ];

            $grupoClean = $gruposTraducidos[$grupoRaw] ?? ucwords(str_replace(['_', '.'], [' ', ' y '], $grupoRaw));

            // Traducir y formatear la acción
            $accionesTraducidas = [
                'ver_listado' => 'Ver Listado',
                'ver_detail' => 'Ver Detalle',
                'ver_detalle' => 'Ver Detalle',
                'crear' => 'Crear',
                'editar' => 'Editar',
                'verificar' => 'Verificar',
                'reportar' => 'Reportar',
                'emitir_consulta' => 'Emitir Consulta',
                'responder_consulta' => 'Responder Consulta',
                'eliminar' => 'Eliminar',
                'ver' => 'Ver',
                'carga_masiva' => 'Carga Masiva',
                'gestionar' => 'Gestionar',
                'correccion_emergencia' => 'Corrección Emergencia',
                'ver_auditoria' => 'Ver Auditoría',
                'generar_permisos' => 'Generar Permisos',
                'administrar' => 'Administrar',
                'monitorear' => 'Monitorear',
                'archivar' => 'Archivar',
                'asignar' => 'Asignar',
                'transferir' => 'Transferir',
                'cambiar_estado' => 'Cambiar Estado',
                'ver_todos' => 'Ver Todos',
                'configurar_tipos' => 'Configurar Tipos',
                'exportar' => 'Exportar'
            ];

            $accionClean = $accionesTraducidas[$accionRaw] ?? ucwords(str_replace('_', ' ', $accionRaw));

            // Formato de nombre: Grupo/Acción Grupo (ej: Activos/Asignar Activos)
            $nombreModulo = "{$grupoClean}/{$accionClean} {$grupoClean}";

            SoporteCatalogoModulo::updateOrCreate(
                ['permiso_requerido' => $permisoName],
                [
                    'nombre' => $nombreModulo,
                    'activo' => true
                ]
            );
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
