<?php

namespace Tests\Unit\PuntoVenta;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigracionOperacionPdvReversibleTest extends TestCase
{
    use RefreshDatabase;

    public function test_migracion_de_operacion_pdv_es_reversible(): void
    {
        $this->assertTrue(Schema::hasTable('pdv_jornadas'));

        $migracion = require database_path('migrations/2026_09_04_200000_create_pdv_operacion_tables.php');
        $migracion->down();

        $this->assertFalse(Schema::hasTable('pdv_jornadas'));
        $this->assertFalse(Schema::hasTable('pdv_intervalos_operativos'));
        $this->assertFalse(Schema::hasTable('pdv_sucursal_dias'));

        $migracion->up();

        $this->assertTrue(Schema::hasTable('pdv_jornadas'));
        $this->assertTrue(Schema::hasTable('pdv_intervalos_operativos'));
        $this->assertTrue(Schema::hasTable('pdv_sucursal_dias'));
    }
}
