<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class EntornoPruebaSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('EntornoPruebaSeeder no se ejecuta en production.');
        }

        $this->call([
            PermisosActualesSeeder::class,
            CuentaAgenteRevisionSeeder::class,
        ]);
    }
}
