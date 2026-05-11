<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. POBLACIÓN DE PERMISOS POR MÓDULOS (Nomenclatura modulo.accion)
        $permisos = [
            // Módulo: Solicitudes
            'solicitudes.ver_listado',
            'solicitudes.ver_detalle',
            'solicitudes.crear',
            'solicitudes.editar',
            'solicitudes.verificar',
            'solicitudes.reportar',
            
            // Módulo: Clientes
            'clientes.ver',
            'clientes.crear',
            'clientes.carga_masiva',
            
            // Módulo: Usuarios y Configuración
            'configuracion.ver_auditoria',
            'usuarios.gestionar',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        // 2. ROLES DE JERARQUÍA (Estructura de RRHH)
        $roleSuperAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $roleSuperAdmin->givePermissionTo(Permission::all());

        $roleAdmin = Role::firstOrCreate(['name' => 'Administrador']);
        $roleAdmin->givePermissionTo(Permission::all());

        $roleGerente = Role::firstOrCreate(['name' => 'Gerente']);
        $roleGerente->givePermissionTo([
            'solicitudes.ver_listado',
            'solicitudes.ver_detalle',
            'solicitudes.reportar',
            'configuracion.ver_auditoria'
        ]);

        $roleColaborador = Role::firstOrCreate(['name' => 'Colaborador']);
        // El colaborador por defecto tiene permisos mínimos de visualización
        $roleColaborador->givePermissionTo([
            'solicitudes.ver_listado'
        ]);

        // 3. ROLES OPERATIVOS (Grupos de Permisos Funcionales)
        $grupoVendedor = Role::firstOrCreate(['name' => 'Grupo: Vendedor']);
        $grupoVendedor->givePermissionTo([
            'solicitudes.crear',
            'clientes.ver',
            'clientes.crear'
        ]);

        $grupoVerificador = Role::firstOrCreate(['name' => 'Grupo: Verificador']);
        $grupoVerificador->givePermissionTo([
            'solicitudes.verificar',
            'solicitudes.ver_detalle'
        ]);

        // 4. CREACIÓN DE USUARIO SUPER ADMIN
        $superAdmin = User::firstOrCreate(
            ['email' => 'realloyal1a@gmail.com'],
            [
                'name' => 'Jesus Gabriel',
                'username' => 'realloyal1a',
                'apellido_paterno' => 'de la Cruz',
                'apellido_materno' => 'Zárate',
                'password' => Hash::make('12345678'),
                'telefono' => '0000000000',
                'fecha_nacimiento' => '1999-01-01', // Fecha placeholder
                'area_id' => null,
                'catalogo_sexo_id' => null,
            ]
        );
        
        // Asignación del rol maestro
        $superAdmin->assignRole('Super Admin');
    }
}