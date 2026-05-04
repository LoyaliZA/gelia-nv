<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Población de permisos granulares
        $permisos = [
            'crear_solicitud',
            'confirmar_pago',
            'verificar_auxiliar',
            'ejecutar_tags',
            'gestionar_usuarios',
            'cargar_clientes_masivo',
            'ver_auditoria'
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        // 2. Creación de Roles y asignación de paquetes de permisos
        $roleSuperAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $roleSuperAdmin->givePermissionTo(Permission::all());

        $roleAdmin = Role::firstOrCreate(['name' => 'Administrador']);
        $roleAdmin->givePermissionTo(Permission::all());

        $roleVendedor = Role::firstOrCreate(['name' => 'Vendedor']);
        $roleVendedor->givePermissionTo(['crear_solicitud', 'confirmar_pago']);

        $roleEncargado = Role::firstOrCreate(['name' => 'Encargado de TAGS']);
        $roleEncargado->givePermissionTo(['ejecutar_tags', 'ver_auditoria']);

        $roleAuxiliar = Role::firstOrCreate(['name' => 'Auxiliar']);
        $roleAuxiliar->givePermissionTo(['verificar_auxiliar', 'ver_auditoria', 'cargar_clientes_masivo']);

        $roleContador = Role::firstOrCreate(['name' => 'Contador']);
        // Asignar permisos futuros de contabilidad aquí

        // 3. Crear usuario Super Admin (Entorno de pruebas)
        $superAdmin = User::firstOrCreate(
            ['email' => 'realloyal1a@gmail.com'],
            [
                'name' => 'Gabriel',
                'username' => 'realloyal1a',
                'apellido_paterno' => 'Admin',
                'password' => Hash::make('12345678'),
                'telefono' => '0000000000',
            ]
        );
        $superAdmin->assignRole('Super Admin');
    }
}