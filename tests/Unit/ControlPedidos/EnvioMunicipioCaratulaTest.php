<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Support\ControlPedidos\MaquinaEstadosTareaPreparacion;
use App\Support\ControlPedidos\VisibilidadTareaPreparacion;
use PHPUnit\Framework\TestCase;

class EnvioMunicipioCaratulaTest extends TestCase
{
    public function test_codigos_fase6_en_solicitables(): void
    {
        $this->assertContains(
            CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO,
            CatalogoModalidadPreparacionPedido::CODIGOS_SOLICITABLES
        );
        $this->assertContains(
            CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO,
            CatalogoModalidadPreparacionPedido::CODIGOS_FASE6
        );
    }

    public function test_transicion_lista_caratula_a_respondida(): void
    {
        $this->assertTrue(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA
        ));
        $this->assertTrue(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA,
            PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA
        ));
        $this->assertFalse(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA,
            PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO
        ));
    }

    public function test_enmascarar_telefono(): void
    {
        $this->assertSame('****5678', VisibilidadTareaPreparacion::enmascararTelefono('5512345678'));
        $this->assertSame('****', VisibilidadTareaPreparacion::enmascararTelefono('12'));
    }

    public function test_paqueteria_no_habilitada_sin_flags(): void
    {
        $paq = new CatalogoPaqueteriaPedido([
            'activo' => true,
            'habilitado_envio_municipio' => false,
            'reglas_municipio_pendientes' => true,
        ]);
        $this->assertFalse($paq->habilitadaParaEnvioMunicipio());
    }

    public function test_caratula_estados_cobro(): void
    {
        $this->assertSame('PAGADO', PedidoBmaCaratula::COBRO_PAGADO);
        $this->assertSame('POR_COBRAR', PedidoBmaCaratula::COBRO_POR_COBRAR);
        $this->assertSame('GENERADA', PedidoBmaCaratula::ESTADO_GENERADA);
        $this->assertSame('COLOCADA', PedidoBmaCaratula::ESTADO_COLOCADA);
    }

    public function test_blade_caratula_jerarquia(): void
    {
        $blade = file_get_contents(dirname(__DIR__, 3).'/resources/views/control_pedidos/caratula.blade.php');
        $this->assertStringContainsString('municipio_destino', $blade);
        $this->assertStringContainsString('destinatario_nombre', $blade);
        $this->assertStringContainsString('POR COBRAR', $blade);
        $this->assertStringContainsString('GELIA', $blade);
        $this->assertDoesNotMatchRegularExpression('/Jaguar|TNT/i', $blade);
    }

    public function test_render_pdf_texto_largo_acentos_smoke(): void
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $this->markTestSkipped('DomPDF no disponible en este entorno.');
        }

        $html = view('control_pedidos.caratula', [
            'caratula' => [
                'municipio_destino' => 'San Andrés Cholula — Zona Industrial Norte con nombre muy largo para prueba de corte',
                'destinatario_nombre' => 'José María Núñez Pérez-García',
                'destinatario_telefono' => '222 123 4567 ext. 89',
                'transporte' => 'Transporte Local',
                'modalidad_cobro' => 'POR_COBRAR',
                'folio' => 'REM-ÁÉÍÓÚ-001',
                'version' => 2,
                'fecha' => '24/08/2026 17:00',
                'direccion_referencia' => 'Calle Prolongación Ñandú #1234',
            ],
        ])->render();

        $this->assertStringContainsString('José María Núñez', $html);
        $this->assertStringContainsString('San Andrés Cholula', $html);
        $this->assertStringContainsString('POR COBRAR', $html);
    }
}
