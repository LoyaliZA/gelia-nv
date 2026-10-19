<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaAnexoEnvio;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaEnvioResguardo;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EnvioMasivoResguardosTest extends TestCase
{
    public function test_solo_envio_exige_anexo_sin_ser_resguardo(): void
    {
        $pedido = new PedidoBma([
            'envio_solo_resguardos' => true,
            'es_resguardo' => false,
        ]);
        $pedido->setRelation('resguardosAnexados', new Collection());
        $pedido->setRelation('cajas', new Collection());
        $pedido->setRelation('anexosEnvio', new Collection());

        $this->assertTrue($pedido->esEnvioSoloResguardos());
        $this->assertTrue($pedido->requiereAnexoPagoEnvioResguardos());
        $this->assertFalse($pedido->anexoEnvioResguardosAprobado());
    }

    public function test_un_apartado_no_es_envio_solo(): void
    {
        $pedido = new PedidoBma([
            'envio_solo_resguardos' => true,
            'es_resguardo' => true,
        ]);

        $this->assertFalse($pedido->esEnvioSoloResguardos());
    }

    public function test_varios_envios_con_seguro_exigen_anexo_adicional(): void
    {
        $pedido = new PedidoBma([
            'envio_solo_resguardos' => false,
            'es_resguardo' => false,
            'aplica_seguro' => true,
            'numero_cajas' => 2,
            'notas_resguardos_externos' => 'Remisión 1546',
        ]);
        $pedido->setRelation('cajas', new Collection([
            new PedidoBmaCaja(['estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA]),
            new PedidoBmaCaja(['estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA]),
        ]));
        $pedido->setRelation('resguardosAnexados', new Collection([new PedidoBmaEnvioResguardo()]));
        $pedido->setRelation('anexosEnvio', new Collection([
            new PedidoBmaAnexoEnvio([
                'estatus' => PedidoBmaAnexoEnvio::ESTATUS_APROBADO,
                'tipo' => PedidoBmaAnexoEnvio::TIPO_ENVIO_ADICIONAL,
            ]),
        ]));

        $this->assertTrue($pedido->requiereAnexoPagoEnvioResguardos());
        $this->assertTrue($pedido->anexoEnvioResguardosAprobado());
    }
}
