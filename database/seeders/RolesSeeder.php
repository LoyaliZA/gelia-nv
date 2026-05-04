<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Poblar catálogo de sexos
        DB::table('catalogo_sexos')->insertOrIgnore([
            ['nombre' => 'Femenino'],
            ['nombre' => 'Masculino'],
            ['nombre' => 'Prefiero no decirlo']
        ]);

        // 2. Poblar Roles (Manteniendo neutralidad de género)
        $roles = [
            'Super Admin', 
            'Administrador', 
            'Vendedor', 
            'Encargado de TAGS', 
            'Auxiliar',
            'Contador' 
        ];

        foreach ($roles as $rol) {
            Role::firstOrCreate(['name' => $rol]);
        }

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

        // Asignación de rol mediante Spatie
        $superAdmin->assignRole('Super Admin');
    }
}