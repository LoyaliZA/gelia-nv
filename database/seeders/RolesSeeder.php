<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Creación de permisos atómicos
        $permisos = [
            'solicitudes.ver_listado',
            'solicitudes.ver_detalle',
            'solicitudes.crear',
            'solicitudes.editar',
            'solicitudes.verificar',
            'solicitudes.reportar',
            'solicitudes.consultar',
            'solicitudes.eliminar',
            'clientes.ver',
            'clientes.crear',
            'clientes.carga_masiva',
            'configuracion.ver_auditoria',
            'usuarios.gestionar',
            'usuarios.generar_permisos',
            'catalogos.comisiones.ver',
            'catalogos.comisiones.gestionar'
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso);
        }

        // 2. Creación de Roles limpios (Sin preasignación)
        Role::findOrCreate('Administrador');
        Role::findOrCreate('Gerente');
        Role::findOrCreate('Colaborador');

        // 3. Creación y asignación absoluta para el Super Admin
        $roleSuperAdmin = Role::findOrCreate('Super Admin');
        $roleSuperAdmin->syncPermissions(Permission::all());

        // 4. Creación del usuario raíz
        $sexoHombre = DB::table('catalogo_sexos')->where('nombre', 'Hombre')->first();

        $user = User::updateOrCreate(
            ['email' => 'realloyal1a@gmail.com'],
            [
                'name' => 'Jesus Gabriel',
                'username' => 'realloyal1a',
                'apellido_paterno' => 'de la Cruz',
                'apellido_materno' => 'Zárate',
                'password' => Hash::make('12345678'),
                'telefono' => '0000000000',
                'fecha_nacimiento' => '1999-01-01',
                'catalogo_sexo_id' => $sexoHombre ? $sexoHombre->id : null,
            ]
        );

        $user->assignRole($roleSuperAdmin);
        $user->syncPermissions(Permission::all());
    }
}