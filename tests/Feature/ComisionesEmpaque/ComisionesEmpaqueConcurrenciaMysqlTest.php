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

    public function test_sincronizacion_completa_y_worker_concurrentes_no_duplican_un_empaque(): void
    {
        $this->assertSame(0, $this->carrera('sincronizar'));
    }

    public function test_empaque_confirmado_despues_de_comprobar_fuentes_queda_como_tardio_una_sola_vez(): void
    {
        $this->assertSame(0, $this->carrera('empaque-durante-cierre'));
    }

    private function carrera(string $caso): int
    {
        $proceso = proc_open(
            [PHP_BINARY, __DIR__.'/concurrencia.php', $caso],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $canales
        );
        $this->assertIsResource($proceso);
        $salida = stream_get_contents($canales[1]);
        $error = stream_get_contents($canales[2]);
        fclose($canales[1]);
        fclose($canales[2]);
        $codigo = proc_close($proceso);
        $this->assertStringContainsString('VERIFICADO', $salida, $salida.$error);

        return $codigo;
    }
}
