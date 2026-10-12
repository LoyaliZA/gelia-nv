<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\Almacen;
use App\Services\ControlPedidos\ValidarAlmacenSalidaPedido;
use Illuminate\Validation\ValidationException;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class ValidarAlmacenSalidaPedidoTest extends TestCase
{
    use RefreshDatabaseSafe;

    public function test_rechaza_salida_nueva_y_conserva_el_almacen_ya_guardado(): void
    {
        $bloqueado = Almacen::query()->create([
            'codigo' => 'CC-TEST',
            'nombre' => 'City Center prueba',
            'activo' => true,
            'permite_salida_pedidos' => false,
        ]);
        $libre = Almacen::query()->create([
            'codigo' => 'CEDIS-TEST',
            'nombre' => 'CEDIS prueba',
            'activo' => true,
            'permite_salida_pedidos' => true,
        ]);

        $validador = app(ValidarAlmacenSalidaPedido::class);
        $validador->ejecutar($libre->id);
        $validador->ejecutar($bloqueado->id, $bloqueado->id);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('no permite salida de mercancía');
        $validador->ejecutar($bloqueado->id);
    }
}
