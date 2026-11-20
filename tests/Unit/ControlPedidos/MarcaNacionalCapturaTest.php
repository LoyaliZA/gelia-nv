<?php

namespace Tests\Unit\ControlPedidos;

use App\Services\ControlPedidos\FusionarSolicitudPreparacionPedido;
use PHPUnit\Framework\TestCase;

class MarcaNacionalCapturaTest extends TestCase
{
    public function test_el_historico_nacional_no_recibe_la_marca_al_fusionar(): void
    {
        $salida = (new FusionarSolicitudPreparacionPedido)->ejecutar(
            ['modalidad_entrega' => 'nacional', 'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL'],
            [
                'modalidad_entrega' => 'nacional',
                'captura_nacional' => 'compartida',
                'area_revision' => 'control_pedidos',
            ],
        );

        $this->assertIsArray($salida);
        $this->assertSame('nacional', $salida['modalidad_entrega']);
        $this->assertArrayNotHasKey('captura_nacional', $salida);
    }

    public function test_el_alta_nueva_guarda_la_marca_y_quita_la_revision_sin_pesaje(): void
    {
        $salida = (new FusionarSolicitudPreparacionPedido)->ejecutar(null, [
            'modalidad_entrega' => 'nacional',
            'captura_nacional' => 'compartida',
            'area_revision' => 'control_pedidos',
        ]);

        $this->assertSame('compartida', $salida['captura_nacional']);
        $this->assertSame('ENVIO_BODEGA_NORMAL', $salida['codigo_modalidad']);
        $this->assertArrayNotHasKey('area_revision', $salida);
    }

    public function test_la_marca_y_los_ajustes_se_conservan_si_la_entrada_no_los_trae(): void
    {
        $salida = (new FusionarSolicitudPreparacionPedido)->ejecutar([
            'modalidad_entrega' => 'nacional',
            'captura_nacional' => 'compartida',
            'ajustes_nacionales' => [['solicitud_id' => '11111111-1111-4111-8111-111111111111']],
        ], ['modalidad_entrega' => 'nacional']);

        $this->assertSame('compartida', $salida['captura_nacional']);
        $this->assertSame('11111111-1111-4111-8111-111111111111', $salida['ajustes_nacionales'][0]['solicitud_id']);
    }

    public function test_leer_un_historico_no_inventa_la_marca(): void
    {
        $salida = (new FusionarSolicitudPreparacionPedido)->normalizar(['modalidad_entrega' => 'nacional']);

        $this->assertIsArray($salida);
        $this->assertArrayNotHasKey('captura_nacional', $salida);
    }
}
