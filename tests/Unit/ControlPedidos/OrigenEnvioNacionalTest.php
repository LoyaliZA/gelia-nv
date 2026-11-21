<?php

namespace Tests\Unit\ControlPedidos;

use App\Support\ControlPedidos\Nacional\OrigenEnvioNacional;
use PHPUnit\Framework\TestCase;

class OrigenEnvioNacionalTest extends TestCase
{
    public function test_un_unico_candidato_activo_con_logistica_devuelve_su_id_sin_importar_el_nombre(): void
    {
        foreach (['ENVÍO', 'Envío Foráneo', 'Otro nombre'] as $nombre) {
            $this->assertSame(3, OrigenEnvioNacional::idUnico([
                ['id' => 2, 'nombre' => 'TIENDA', 'requiere_logistica' => false, 'activo' => true],
                ['id' => 3, 'nombre' => $nombre, 'requiere_logistica' => true, 'activo' => true],
            ]));
        }
    }

    public function test_sin_candidatos_o_con_varios_no_elige_ninguno(): void
    {
        $this->assertNull(OrigenEnvioNacional::idUnico([]));
        $this->assertNull(OrigenEnvioNacional::idUnico([
            ['id' => 1, 'requiere_logistica' => false, 'activo' => true],
            ['id' => 4, 'requiere_logistica' => true, 'activo' => false],
        ]));
        $this->assertNull(OrigenEnvioNacional::idUnico([
            ['id' => 3, 'requiere_logistica' => true, 'activo' => true],
            ['id' => 5, 'requiere_logistica' => true, 'activo' => true],
        ]));
        $this->assertSame([3, 5], OrigenEnvioNacional::candidatos([
            ['id' => 3, 'requiere_logistica' => true, 'activo' => true],
            ['id' => 5, 'requiere_logistica' => '1', 'activo' => true],
            ['id' => 3, 'requiere_logistica' => true, 'activo' => true],
        ]));
    }
}
