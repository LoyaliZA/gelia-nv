<?php

namespace Tests\Unit\PuntoVenta;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigracionResguardosPdvReversibleTest extends TestCase
{
    use RefreshDatabase;

    public function test_migracion_de_resguardos_pdv_es_reversible(): void
    {
        $this->assertTrue(Schema::hasTable('pdv_resguardos'));

        $migracion = require database_path('migrations/2026_09_01_190000_create_pdv_resguardos_tables.php');
        $migracion->down();

        $this->assertFalse(Schema::hasTable('pdv_resguardos'));
        $this->assertFalse(Schema::hasTable('pdv_resguardo_bultos'));
        $this->assertFalse(Schema::hasTable('pdv_resguardo_evidencias'));

        $migracion->up();

        $this->assertTrue(Schema::hasTable('pdv_resguardos'));
        $this->assertTrue(Schema::hasTable('pdv_resguardo_evidencias'));
        $this->assertTrue(Schema::hasTable('pdv_resguardo_entrega_bultos'));
    }
}
