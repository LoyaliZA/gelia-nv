<?php

namespace Tests\Feature\ComisionesEmpaque;

use PHPUnit\Framework\TestCase;

/**
 * Carreras reales sobre MySQL aislado. No usa la base de trabajo ni RefreshDatabase.
 * Se omite salvo COMISIONES_EMPAQUE_MYSQL=1 y DB_DATABASE distinto de laravel.
 */
class ComisionesEmpaqueConcurrenciaMysqlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('COMISIONES_EMPAQUE_MYSQL') !== '1') {
            $this->markTestSkipped('MySQL aislado no solicitado. Defina COMISIONES_EMPAQUE_MYSQL=1 y una base distinta de laravel.');
        }
        if (getenv('DB_DATABASE') === 'laravel' || getenv('DB_DATABASE') === '' || getenv('DB_DATABASE') === false) {
            $this->markTestSkipped('La base indicada no es un esquema aislado.');
        }
    }

    public function test_dos_confirmaciones_concurrentes_no_incluyen_la_misma_unidad(): void
    {
        $this->assertSame(0, $this->carrera('confirmar'));
    }

    public function test_confirmar_y_clasificar_la_misma_unidad_no_dejan_un_corte_parcial(): void
    {
        $this->assertSame(0, $this->carrera('confirmar-clasificar'));
    }

    public function test_dos_pagos_concurrentes_con_la_misma_clave_no_duplican_el_importe(): void
    {
        $this->assertSame(0, $this->carrera('pagar'));
    }

    public function test_dos_capturas_concurrentes_con_la_misma_clave_dejan_un_registro(): void
    {
        $this->assertSame(0, $this->carrera('capturar'));
    }

    private function carrera(string $caso): int
    {
        $script = __DIR__.'/concurrencia.php';
        $procesos = [];
        foreach ([1, 2] as $n) {
            $procesos[$n] = proc_open(
                [PHP_BINARY, $script, $caso, (string) $n],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            $this->assertIsResource($procesos[$n]);
            $procesos[$n.'_pipes'] = $pipes;
        }

        $codigos = [];
        foreach ([1, 2] as $n) {
            $codigos[$n] = proc_close($procesos[$n]);
        }

        return max($codigos);
    }
}
