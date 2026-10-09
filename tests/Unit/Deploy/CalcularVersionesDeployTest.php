<?php

namespace Tests\Unit\Deploy;

use App\Support\Deploy\CalcularVersionesDeploy;
use Tests\TestCase;

class CalcularVersionesDeployTest extends TestCase
{
    public function test_un_chunk_ajeno_no_cambia_sala_ni_turnos(): void
    {
        $base = $this->manifiesto();
        $alterado = $this->manifiesto();
        $alterado['resources/js/Pages/Facturas/Index.jsx']['file'] = 'assets/Facturas-OTRO.js';

        $antes = CalcularVersionesDeploy::desdeManifiesto($base);
        $despues = CalcularVersionesDeploy::desdeManifiesto($alterado);

        $this->assertSame($antes['surfaces']['sala'], $despues['surfaces']['sala']);
        $this->assertSame($antes['surfaces']['turnos'], $despues['surfaces']['turnos']);
        $this->assertSame($antes['shell'], $despues['shell']);
    }

    public function test_un_chunk_de_sala_solo_cambia_esa_superficie(): void
    {
        $base = $this->manifiesto();
        $alterado = $this->manifiesto();
        $alterado['resources/js/Pages/PuntoVenta/Pantallas/Sala.jsx']['file'] = 'assets/Sala-NUEVA.js';

        $antes = CalcularVersionesDeploy::desdeManifiesto($base);
        $despues = CalcularVersionesDeploy::desdeManifiesto($alterado);

        $this->assertNotSame($antes['surfaces']['sala'], $despues['surfaces']['sala']);
        $this->assertSame($antes['surfaces']['turnos'], $despues['surfaces']['turnos']);
        $this->assertSame($antes['shell'], $despues['shell']);
    }

    public function test_el_shell_cambia_con_el_arranque_comun_y_no_con_una_pagina(): void
    {
        $base = $this->manifiesto();
        $pagina = $this->manifiesto();
        $pagina['resources/js/Pages/PuntoVenta/Turnos/Recepcion.jsx']['file'] = 'assets/Recepcion-NUEVA.js';
        $arranque = $this->manifiesto();
        $arranque['resources/js/app.jsx']['file'] = 'assets/app-NUEVO.js';

        $antes = CalcularVersionesDeploy::desdeManifiesto($base);

        $this->assertSame($antes['shell'], CalcularVersionesDeploy::desdeManifiesto($pagina)['shell']);
        $this->assertNotSame($antes['shell'], CalcularVersionesDeploy::desdeManifiesto($arranque)['shell']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function manifiesto(): array
    {
        return [
            'resources/js/app.jsx' => [
                'file' => 'assets/app-SHELL.js',
                'imports' => ['_vendor.js'],
                'dynamicImports' => [
                    'resources/js/Pages/PuntoVenta/Pantallas/Sala.jsx',
                    'resources/js/Pages/Facturas/Index.jsx',
                ],
            ],
            '_vendor.js' => [
                'file' => 'assets/vendor-VENDOR.js',
            ],
            'resources/css/app.css' => [
                'file' => 'assets/app-CSS.css',
            ],
            'resources/js/Pages/PuntoVenta/Pantallas/Sala.jsx' => [
                'file' => 'assets/Sala-SALA.js',
                'imports' => ['_vendor.js'],
            ],
            'resources/js/Pages/PuntoVenta/Turnos/Recepcion.jsx' => [
                'file' => 'assets/Recepcion-TURNOS.js',
                'imports' => ['_compartido.js'],
            ],
            'resources/js/Pages/PuntoVenta/Turnos/Ventas.jsx' => [
                'file' => 'assets/Ventas-TURNOS.js',
                'imports' => ['_compartido.js'],
            ],
            '_compartido.js' => [
                'file' => 'assets/compartido-COMP.js',
            ],
            'resources/js/Pages/Facturas/Index.jsx' => [
                'file' => 'assets/Facturas-FACT.js',
            ],
        ];
    }
}
