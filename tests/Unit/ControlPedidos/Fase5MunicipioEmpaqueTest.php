<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Support\ControlPedidos\MaquinaEstadosCumplimientoFisico;
use App\Support\ControlPedidos\MaquinaEstadosTareaPreparacion;
use App\Support\ControlPedidos\PoliticaSalidaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use PHPUnit\Framework\TestCase;

class Fase5MunicipioEmpaqueTest extends TestCase
{
    public function test_caratura_no_exige_remision_en_validar_respuesta(): void
    {
        $src = file_get_contents(dirname(__DIR__, 3).'/app/Services/ControlPedidos/CalcularRequisitosPreparacionService.php');
        $respuesta = substr($src, 0, (int) strpos($src, 'function validarDocumentosCaratulaMunicipio'));
        $this->assertStringNotContainsString("faltantes[] = 'Remisión'", $respuesta);
    }

    public function test_validar_caratula_sin_remision(): void
    {
        $caratula = substr(
            file_get_contents(dirname(__DIR__, 3).'/app/Services/ControlPedidos/CalcularRequisitosPreparacionService.php'),
            (int) strpos(file_get_contents(dirname(__DIR__, 3).'/app/Services/ControlPedidos/CalcularRequisitosPreparacionService.php'), 'function validarDocumentosCaratulaMunicipio'),
            900
        );
        $this->assertStringNotContainsString('TIPO_REMISION', $caratula);
    }

    public function test_hoja_salida_interna_tipificada(): void
    {
        $this->assertSame('hoja_salida_interna', PedidoBmaTareaDocumento::TIPO_HOJA_SALIDA_INTERNA);
        $blade = file_get_contents(dirname(__DIR__, 3).'/resources/views/control_pedidos/hoja_salida_interna.blade.php');
        $this->assertStringContainsString('No es remisión', $blade);
    }

    public function test_municipio_usa_salida_interna_y_puede_por_cobrar_desde_transporte(): void
    {
        $paq = new \App\Models\ControlPedidos\CatalogoPaqueteriaPedido(['permite_por_cobrar' => true]);
        $politica = new PoliticaSalidaPreparacion(
            CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO,
            $paq
        );
        $this->assertTrue($politica->usaSalidaInterna());
        $this->assertTrue($politica->permitePorCobrar());
        $this->assertFalse($politica->cierraEnPdv());
    }

    public function test_flujo_empaque_municipio_en_maquina(): void
    {
        $this->assertTrue(MaquinaEstadosCumplimientoFisico::puedeTransicionar(
            PedidoBmaCumplimientoFisico::ESTADO_LISTA_PARA_SALIDA,
            PedidoBmaCumplimientoFisico::ESTADO_EMPACADA
        ));
        $this->assertTrue(MaquinaEstadosCumplimientoFisico::puedeTransicionar(
            PedidoBmaCumplimientoFisico::ESTADO_EMPACADA,
            PedidoBmaCumplimientoFisico::ESTADO_DESPACHADA
        ));
    }

    public function test_respondida_puede_volver_a_lista_caratula_tras_invalidacion(): void
    {
        $this->assertTrue(MaquinaEstadosTareaPreparacion::puedeTransicionar(
            PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA
        ));
    }
}
