<?php

namespace Tests\Unit\Solicitudes;

use App\Models\Cliente;
use App\Services\Solicitudes\TipoClienteSolicitudResolver;
use PHPUnit\Framework\TestCase;

class TipoClienteSolicitudResolverTest extends TestCase
{
    public function test_prefiere_tipo_explicito_sobre_ficha_cliente(): void
    {
        $cliente = new Cliente(['catalogo_tipo_cliente_id' => 2]);
        $this->assertSame(5, TipoClienteSolicitudResolver::resolver(5, $cliente));
    }

    public function test_usa_tipo_de_ficha_si_formulario_vacio(): void
    {
        $cliente = new Cliente(['catalogo_tipo_cliente_id' => 3]);
        $this->assertSame(3, TipoClienteSolicitudResolver::resolver(null, $cliente));
    }

    public function test_falta_tipo_cuando_ambos_ausentes(): void
    {
        $this->assertTrue(TipoClienteSolicitudResolver::faltaTipoComercial(
            TipoClienteSolicitudResolver::resolver(null, new Cliente())
        ));
    }
}
