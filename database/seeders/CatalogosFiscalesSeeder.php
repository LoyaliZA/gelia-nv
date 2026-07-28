<?php

namespace Database\Seeders;

use App\Services\Facturas\ImportarCatalogosFiscalesService;
use Illuminate\Database\Seeder;

class CatalogosFiscalesSeeder extends Seeder
{
    public function run(): void
    {
        $resultado = app(ImportarCatalogosFiscalesService::class)->ejecutar();

        $this->command?->info(
            "Catálogos fiscales: {$resultado['regimen']} regímenes, {$resultado['uso_cfdi']} usos de CFDI."
        );
    }
}
