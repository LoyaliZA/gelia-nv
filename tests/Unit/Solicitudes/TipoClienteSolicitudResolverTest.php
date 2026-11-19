<?php

namespace Tests\Unit\Solicitudes;

use App\Models\CatalogoTipoCliente;
use App\Models\Cliente;
use App\Services\Solicitudes\TipoClienteSolicitudResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TipoClienteSolicitudResolverTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoTipoCliente $activo;

    private CatalogoTipoCliente $nuevo;

    private CatalogoTipoCliente $reactivado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activo = CatalogoTipoCliente::create(['nombre' => 'ACTIVO', 'activo' => true]);
        $this->nuevo = CatalogoTipoCliente::create(['nombre' => 'NUEVO', 'activo' => true]);
        $this->reactivado = CatalogoTipoCliente::create(['nombre' => 'REACTIVADO', 'activo' => true]);
        CatalogoTipoCliente::reiniciarCache();
    }

    public function test_prefiere_tipo_explicito_sobre_ficha_cliente(): void
    {
        $cliente = new Cliente(['catalogo_tipo_cliente_id' => $this->activo->id, 'es_inactivo' => false]);
        $this->assertSame($this->nuevo->id, TipoClienteSolicitudResolver::resolver($this->nuevo->id, $cliente));
    }

    public function test_usa_tipo_de_ficha_si_formulario_vacio(): void
    {
        $cliente = new Cliente(['catalogo_tipo_cliente_id' => $this->nuevo->id, 'es_inactivo' => false]);
        $this->assertSame($this->nuevo->id, TipoClienteSolicitudResolver::resolver(null, $cliente));
    }

    public function test_inactivo_resuelve_reactivado(): void
    {
        $cliente = new Cliente(['catalogo_tipo_cliente_id' => $this->nuevo->id, 'es_inactivo' => true]);
        $this->assertSame($this->reactivado->id, TipoClienteSolicitudResolver::resolver($this->activo->id, $cliente));
    }

    public function test_activo_no_conserva_reactivado_de_ficha(): void
    {
        $cliente = new Cliente(['catalogo_tipo_cliente_id' => $this->reactivado->id, 'es_inactivo' => false]);
        $this->assertSame($this->activo->id, TipoClienteSolicitudResolver::resolver(null, $cliente));
    }

    public function test_falta_tipo_cuando_ambos_ausentes(): void
    {
        $this->assertTrue(TipoClienteSolicitudResolver::faltaTipoComercial(
            TipoClienteSolicitudResolver::resolver(null, new Cliente())
        ));
    }
}
