<?php

namespace Tests\Unit\PuntoVenta;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigracionTurnosPdvReversibleTest extends TestCase
{
    use RefreshDatabase;

    public function test_migracion_de_turnos_pdv_es_reversible(): void
    {
        $this->assertTrue(Schema::hasTable('pdv_turnos'));

        $migracion = require database_path('migrations/2026_09_04_120000_create_pdv_turnos_tables.php');
        $migracion->down();

        $this->assertFalse(Schema::hasTable('pdv_turnos'));
        $this->assertFalse(Schema::hasTable('pdv_turno_atenciones'));
        $this->assertFalse(Schema::hasTable('pdv_contadores_folio'));

        $migracion->up();

        $this->assertTrue(Schema::hasTable('pdv_turnos'));
        $this->assertTrue(Schema::hasTable('pdv_turno_eventos'));
        $this->assertTrue(Schema::hasTable('pdv_contadores_folio'));
    }
}
