<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoTipoOperacionEnvio;
use App\Models\ControlPedidos\OperacionEmpaque;
use App\Models\ControlPedidos\OperacionEmpaqueMiembro;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\ValidacionCamposPedidoBma;
use Tests\TestCase;

/**
 * Check legado Fase 3 + corrección Fase 5: complementario → completo; tablas empaque deprecadas.
 */
class ControlPedidosConsolidacionFase3Test extends TestCase
{
    private function resolverEstatus(): object
    {
        return new class {
            use ValidacionCamposPedidoBma;

            public function estatus(PedidoBma $pedido): string
            {
                return $this->resolverEstatusEnvioAlEnviar($pedido);
            }
        };
    }

    public function test_complementario_al_enviar_queda_completo(): void
    {
        $tipo = new CatalogoTipoOperacionEnvio([
            'codigo' => CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_COMPLEMENTARIO,
        ]);
        $pedido = new PedidoBma(['costo_envio' => 80]);
        $pedido->setRelation('tipoOperacionEnvio', $tipo);

        $this->assertTrue($pedido->esResguardoComplementario());
        $this->assertFalse($pedido->esResguardoAbierto());
        $this->assertSame(
            PedidoBma::ESTATUS_ENVIO_COMPLETO,
            $this->resolverEstatus()->estatus($pedido)
        );
    }

    public function test_constantes_legado_y_flag_con_complementos(): void
    {
        $this->assertSame('RESGUARDO_COMPLEMENTARIO', CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_COMPLEMENTARIO);
        $this->assertSame('consolidado', PedidoBma::ESTATUS_ENVIO_CONSOLIDADO);
        $this->assertSame('pendiente_consolidacion', PedidoBma::ESTATUS_ENVIO_PENDIENTE_CONSOLIDACION);

        $raiz = new PedidoBma(['folio' => 'PBMA-2026-00001']);
        $raiz->setRelation('complementos', collect([new PedidoBma(['pedido_principal_id' => 1])]));
        $this->assertTrue($raiz->estaConsolidado());

        $tipo = new CatalogoTipoOperacionEnvio([
            'codigo' => CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_COMPLEMENTARIO,
        ]);
        $this->assertTrue($tipo->esResguardoComplementario());
    }

    public function test_tablas_operacion_empaque_siguen_modeladas_deprecadas(): void
    {
        $op = new OperacionEmpaque([
            'folio_operacion' => 'EMP-20260727-0001',
            'estatus' => OperacionEmpaque::ESTATUS_ABIERTA,
        ]);
        $m1 = new OperacionEmpaqueMiembro(['cantidad_piezas' => 4, 'es_principal' => true]);
        $m2 = new OperacionEmpaqueMiembro(['cantidad_piezas' => 1, 'es_principal' => false]);
        $op->setRelation('miembros', collect([$m1, $m2]));

        $this->assertSame(5, $op->sumaPiezas());
        $this->assertTrue($op->estaAbierta());
        $this->assertSame('operaciones_empaque', $op->getTable());
        $this->assertSame('operacion_empaque_miembros', $m1->getTable());
    }
}
