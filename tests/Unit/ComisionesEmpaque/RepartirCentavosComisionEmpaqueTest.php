<?php

namespace Tests\Unit\ComisionesEmpaque;

use App\Support\ComisionesEmpaque\RepartirCentavosComisionEmpaque;
use PHPUnit\Framework\TestCase;

class RepartirCentavosComisionEmpaqueTest extends TestCase
{
    public function test_el_reparto_cierra_en_el_importe_y_asigna_el_centavo_por_residuo(): void
    {
        $montos = (new RepartirCentavosComisionEmpaque)->repartir(100, [
            ['participante_id' => 2, 'puntos_base' => 3333],
            ['participante_id' => 1, 'puntos_base' => 3334],
            ['participante_id' => 3, 'puntos_base' => 3333],
        ]);

        $this->assertSame(100, array_sum($montos));
        $this->assertSame(34, $montos[1]);
        $this->assertSame(33, $montos[2]);
        $this->assertSame(33, $montos[3]);
    }

    public function test_el_empate_de_residuo_favorece_al_id_menor(): void
    {
        $montos = (new RepartirCentavosComisionEmpaque)->repartir(1, [
            ['participante_id' => 8, 'puntos_base' => 5000],
            ['participante_id' => 3, 'puntos_base' => 5000],
        ]);

        $this->assertSame(1, array_sum($montos));
        $this->assertSame(1, $montos[3]);
        $this->assertSame(0, $montos[8]);
    }
}
