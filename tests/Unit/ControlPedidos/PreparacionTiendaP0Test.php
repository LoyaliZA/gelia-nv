<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaReferencia;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaProducto;
use App\Services\ControlPedidos\CalcularRequisitosPreparacionService;
use App\Support\ControlPedidos\DesgloseSkuPreparacion;
use Tests\TestCase;

class PreparacionTiendaP0Test extends TestCase
{
    public function test_renglon_generico_sin_sku_bloquea_respuesta(): void
    {
        $tarea = new PedidoBmaTareaPreparacion;
        $tarea->setRelation('productos', collect([
            new PedidoBmaTareaProducto([
                'descripcion_snapshot' => 'Piezas del pedido (3)',
                'cantidad_solicitada' => 3,
            ]),
        ]));

        $this->assertNotNull(DesgloseSkuPreparacion::mensaje($tarea));
    }

    public function test_desglose_con_sku_permite_responder(): void
    {
        $tarea = new PedidoBmaTareaPreparacion;
        $tarea->setRelation('productos', collect([
            new PedidoBmaTareaProducto([
                'sku' => 'SKU-1',
                'descripcion_snapshot' => 'Crema',
                'cantidad_solicitada' => 1,
            ]),
        ]));

        $this->assertNull(DesgloseSkuPreparacion::mensaje($tarea));
    }

    public function test_respuesta_no_exige_remision_y_si_evidencia_por_producto(): void
    {
        $modalidad = new CatalogoModalidadPreparacionPedido([
            'requisitos_json' => [
                'evidencia_por_producto' => true,
                'evidencia_general_obligatoria' => false,
                'requiere_remision' => true,
            ],
        ]);
        $producto = new PedidoBmaTareaProducto([
            'descripcion_snapshot' => 'Crema',
            'cantidad_solicitada' => 1,
        ]);
        $producto->id = 7;

        $tarea = new PedidoBmaTareaPreparacion;
        $tarea->setRelation('modalidad', $modalidad);
        $tarea->setRelation('paqueteria', null);
        $tarea->setRelation('pedido', null);
        $tarea->setRelation('documentos', collect());
        $tarea->setRelation('productos', collect([$producto]));

        $faltantes = app(CalcularRequisitosPreparacionService::class)->validarRespuesta($tarea, [[
            'id' => 7,
            'cantidad_encontrada' => 1,
            'estado_fisico' => 'bueno',
        ]]);

        $this->assertFalse(collect($faltantes)->contains(fn ($f) => str_contains($f, 'Remisión')));
        $this->assertTrue(collect($faltantes)->contains(fn ($f) => str_contains($f, 'Evidencia del producto')));
    }

    public function test_referencia_posterior_no_cambia_folio_interno(): void
    {
        $pedido = new PedidoBma([
            'folio' => 'GEL-100',
            'folio_remision' => 'VIEJO',
        ]);
        $cotizacion = new PedidoBmaReferencia([
            'tipo' => PedidoBmaReferencia::TIPO_COTIZACION,
            'folio' => 'COT-9',
            'vigente' => true,
        ]);
        $cotizacion->id = 1;
        $pedidoRef = new PedidoBmaReferencia([
            'tipo' => PedidoBmaReferencia::TIPO_PEDIDO,
            'folio' => 'PED-20',
            'vigente' => true,
        ]);
        $pedidoRef->id = 2;
        $pedido->setRelation('referencias', collect([$cotizacion, $pedidoRef]));

        $visible = PedidoBmaReferencia::visible($pedido);

        $this->assertSame('GEL-100', $pedido->folio);
        $this->assertSame('PED-20', $visible['folio']);
        $this->assertSame('Folio · Pedido', $visible['etiqueta']);
    }

    public function test_cantidad_cero_no_inventa_pieza_en_traspaso_ni_revision(): void
    {
        $traspaso = file_get_contents(app_path('Services/ControlPedidos/CrearTraspasoDesdeTareaPreparacionService.php'));
        $respuesta = file_get_contents(app_path('Services/ControlPedidos/ResponderPreparacionTiendaService.php'));

        $this->assertStringNotContainsString('max(1,', $traspaso);
        $this->assertStringNotContainsString('max(1,', $respuesta);
        $this->assertStringContainsString('cantidad_encontrada > 0', $traspaso);
    }

    public function test_verificar_traspaso_sincroniza_la_tarea(): void
    {
        $controlador = file_get_contents(app_path('Http/Controllers/Traspasos/SolicitudTraspasoController.php'));
        $salida = file_get_contents(app_path('Services/ControlPedidos/ConfirmarSalidaTrasladoTiendaService.php'));
        $requisitos = file_get_contents(app_path('Services/ControlPedidos/CalcularRequisitosPreparacionService.php'));

        $this->assertStringContainsString('desdeConfirmacion', $controlador);
        $this->assertStringContainsString("idDe('Verificada')", $salida);
        $this->assertStringNotContainsString('|| true', $requisitos);
        $respuesta = substr($requisitos, 0, (int) strpos($requisitos, 'function validarDocumentosMunicipio'));
        $this->assertStringNotContainsString("faltantes[] = 'Remisión'", $respuesta);
    }

    public function test_documento_de_tarea_distingue_evidencia_de_producto(): void
    {
        $this->assertSame('evidencia_producto', PedidoBmaTareaDocumento::TIPO_EVIDENCIA_PRODUCTO);
    }
}
