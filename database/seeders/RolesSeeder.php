<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // Creamos los roles base del sistema
        $roles = [
            'Administrador',
            'Vendedor',
            'Encargado de TAGS',
            'Auxiliar'
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}