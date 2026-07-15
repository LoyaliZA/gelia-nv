<?php

namespace Tests\Unit\ControlPedidos;

use App\Support\ControlPedidos\CodigoDireccionCliente;
use PHPUnit\Framework\TestCase;

class CodigoDireccionClienteTest extends TestCase
{
    public function test_primera_direccion_sin_sufijo(): void
    {
        $this->assertSame('8699', CodigoDireccionCliente::formatear('8699', 1));
        $this->assertSame('8699', CodigoDireccionCliente::formatear('8699', null));
    }

    public function test_direcciones_adicionales_con_sufijo(): void
    {
        $this->assertSame('8699-1', CodigoDireccionCliente::formatear('8699', 2));
        $this->assertSame('8699-2', CodigoDireccionCliente::formatear('8699', 3));
    }
}
