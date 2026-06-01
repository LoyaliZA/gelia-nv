<?php

namespace Tests\Unit\Rh;

use Tests\TestCase;

class StoreReglaIncidenciaValidationTest extends TestCase
{
    public function test_cobro_fijo_requiere_monto_fijo(): void
    {
        $validator = validator(
            ['tipo_comportamiento' => 'cobro_fijo', 'nombre' => 'Nota Faltante'],
            ['monto_fijo' => 'nullable|numeric|min:0|required_if:tipo_comportamiento,cobro_fijo'],
        );

        $this->assertTrue($validator->fails());
    }

    public function test_cancelacion_bono_requiere_catalogo_bono_id(): void
    {
        $validator = validator(
            ['tipo_comportamiento' => 'cancelacion_bono_especifico', 'nombre' => 'Insubordinación'],
            ['catalogo_bono_id' => 'nullable|required_if:tipo_comportamiento,cancelacion_bono_especifico'],
        );

        $this->assertTrue($validator->fails());
    }

    public function test_cobro_fijo_con_monto_es_valido(): void
    {
        $validator = validator(
            ['tipo_comportamiento' => 'cobro_fijo', 'nombre' => 'Nota', 'monto_fijo' => 100],
            ['monto_fijo' => 'nullable|numeric|min:0|required_if:tipo_comportamiento,cobro_fijo'],
        );

        $this->assertFalse($validator->fails());
    }
}
