<?php

namespace Tests\Feature\ControlPedidos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\CerrarConsultaPedidoBmaService;
use App\Services\ControlPedidos\EnviarPedidoBmaService;
use App\Services\ControlPedidos\AprobarPedidoBmaService;
use App\Services\ControlPedidos\ValidarPagoPedidoBmaService;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\PuntoVenta\Resguardos\PasarARecepcionResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\RegistrarConfirmacionCustodiaPdvService;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use App\Services\ControlPedidos\MarcarEnviadoPedidoBmaService;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\ResponderPesajePedidoBmaService;
use App\Services\ControlPedidos\SolicitarPesajePedidoBmaService;
use App\Services\ControlPedidos\ValidacionCamposPedidoBma;
use App\Services\PuntoVenta\Resguardos\CrearRecepcionEsperadaPdvService;
use App\Services\PuntoVenta\Resguardos\RegistrarRecepcionFisicaPdvService;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TrasladosInternosQ6Test extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Storage::fake('public');
        Event::fake();
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        $this->actor = User::factory()->create();
        Permission::findOrCreate('control_pedidos.editar', 'web');
        $this->actor->givePermissionTo('control_pedidos.editar');
        foreach ([CatalogoEstatusPedido::FASE_BORRADOR, CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO, CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR, CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, CatalogoEstatusPedido::FASE_ENVIADO] as $i => $fase) {
            CatalogoEstatusPedido::query()->firstOrCreate(['fase_ciclo' => $fase], [
                'codigo_interno' => 'Q6-'.$i, 'nombre_visual' => $fase, 'color_hex' => '#999999', 'orden' => $i, 'activo' => true,
            ]);
        }
    }

    public function test_cedis_con_logistica_revisa_sin_peso_y_sale_con_bultos_hacia_recepcion_unica(): void
    {
        $this->verificarRecorrido(false);
    }

    public function test_carmen_a_city_revisa_sin_peso_y_sale_con_bultos_hacia_recepcion_unica(): void
    {
        $this->verificarRecorrido(true);
    }

    private function verificarRecorrido(bool $pdv): void
    {
        $pedido = $this->pedido($pdv);
        $solicitado = app(SolicitarPesajePedidoBmaService::class)->ejecutar($pedido, $this->actor->id);
        $this->assertTrue($solicitado->consultaCedisModoRevisionMercancia());
        $respondido = $this->responder($solicitado);
        $this->assertNull($respondido->peso_real_kg);
        $this->assertNull($respondido->peso_volumetrico_kg);
        $this->assertNull($respondido->catalogo_tipo_caja_id);
        $this->assertNull($respondido->catalogo_paqueteria_id);
        $this->assertSame(2, $respondido->numero_cajas);
        $this->assertSame(0, $respondido->cajas()->count());
        $revision = $respondido->revisionesProducto()->sole();
        $this->assertSame(3, $revision->cantidad_solicitada);
        $this->assertSame(3, $revision->cantidad_encontrada);
        $this->assertTrue($revision->unica_pieza);
        $this->assertTrue($revision->mejor_ejemplar);
        $this->assertSame('bueno', $revision->estado_fisico);
        $this->assertNotNull($respondido->pesaje_respondido_at);
        $this->assertSame($this->actor->id, $respondido->pesaje_respondido_por_id);
        $this->assertTrue($respondido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION)->exists());

        app(CerrarConsultaPedidoBmaService::class)->ejecutar($respondido, $this->actor->id);
        $this->validar($respondido->fresh());
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
        $this->assertSame(0, ResguardoPdv::query()->count());

        PedidoBmaPago::query()->create([
            'pedido_bma_id' => $pedido->id, 'numero_exhibicion' => 1, 'monto' => 100,
            'forma_pago' => 'efectivo', 'ruta_archivo' => 'q6/pago.jpg', 'nombre_original' => 'pago.jpg',
            'mime_type' => 'image/jpeg', 'estado_revision' => PedidoBmaPago::REVISION_PENDIENTE,
            'capturado_por_id' => $this->actor->id,
        ]);
        config(['control_pedidos.direcciones_normalizadas' => true]);
        $auxiliar = app(EnviarPedidoBmaService::class)->ejecutar($respondido->fresh(), $this->actor->id);
        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR, $auxiliar->estatus->fase_ciclo);
        app(ValidarPagoPedidoBmaService::class)->ejecutar($auxiliar, $this->actor->id);
        $this->documento($auxiliar, PedidoBmaDocumento::TIPO_REMISION);
        $aprobado = app(AprobarPedidoBmaService::class)->ejecutar($auxiliar->fresh(), $this->actor->id);
        $this->assertSame(CatalogoEstatusPedido::FASE_EN_CEDIS, $aprobado->estatus->fase_ciclo);
        $empacado = app(MarcarEmpacadoPedidoBmaService::class)->ejecutar($aprobado, $this->actor->id, [
            $pedido->id => [
                ['foto_bulto' => UploadedFile::fake()->image('b1.jpg'), 'foto_ticket' => UploadedFile::fake()->image('t1.jpg')],
                ['foto_bulto' => UploadedFile::fake()->image('b2.jpg'), 'foto_ticket' => UploadedFile::fake()->image('t2.jpg')],
            ],
        ]);
        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, $empacado->estatus->fase_ciclo);
        $this->assertSame(2, $empacado->bultosEmpaque()->count());
        $this->assertSame(0, ResguardoPdv::query()->count());

        // El mismo objeto previo a la salida simula dos clics con pantalla desactualizada.
        app(MarcarEnviadoPedidoBmaService::class)->ejecutar($empacado, $this->actor->id);
        $historiales = PedidoBmaHistorialEstado::query()->count();
        app(MarcarEnviadoPedidoBmaService::class)->ejecutar($empacado, $this->actor->id);
        $resguardo = app(CrearRecepcionEsperadaPdvService::class)->ejecutar($pedido->fresh(), $this->actor->id);
        $this->assertSame(1, ResguardoPdv::query()->count());
        $this->assertSame(1, ResguardoPdvEvento::query()->count());
        $this->assertSame($historiales, PedidoBmaHistorialEstado::query()->count());
        $this->assertSame(ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $resguardo->estado);
        $this->assertNull($resguardo->recepcion_fisica_at);
        $this->assertSame(2, $resguardo->cantidad_bultos_esperada);
        $this->assertFalse($resguardo->snapshot_json['inventario_sincronizado']);
        $this->assertSame(3, $resguardo->snapshot_json['revision_fisica'][0]['cantidad_encontrada']);
        $this->assertSame(EstatusSucursalPedidoBma::EN_TRANSITO, $pedido->fresh()->estatus_sucursal);

        $alcance = $this->mock(ResuelveAlcancePdv::class);
        $alcance->shouldReceive('asegurarMutacionPiso')->times(4);
        $alcance->shouldReceive('tienePermisoPdv')->andReturn(false);
        $recibido = app(RegistrarRecepcionFisicaPdvService::class)->ejecutar($resguardo, $this->actor, 1, 'q6:rec:'.$pedido->id, fotoPaquete: UploadedFile::fake()->image('llegada.jpg'), folios: array_column(\App\Support\PuntoVenta\Resguardos\BultosEsperadosResguardoPdv::desdeResguardo($resguardo), 'folio'));
        $this->assertSame(ResguardoPdv::ESTADO_RECIBIDO, $recibido->estado);
        $this->assertNotNull($recibido->recepcion_fisica_at);
        $this->assertSame(EstatusSucursalPedidoBma::RECIBIDO, $pedido->fresh()->estatus_sucursal);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
        $this->assertSame(1, PedidoBma::query()->count());

        $enRecepcion = app(PasarARecepcionResguardoPdvService::class)->ejecutar(
            $recibido, $this->actor, $recibido->version, 'q6:piso:'.$pedido->id
        );
        $almacenDestino = Almacen::query()->create(['codigo' => 'DEST-Q6', 'nombre' => 'Custodia City', 'sucursal_id' => $resguardo->sucursal_id, 'activo' => true]);
        $folios = $enRecepcion->bultos()->orderBy('id')->pluck('folio');
        $parcial = app(RegistrarConfirmacionCustodiaPdvService::class)->ejecutar(
            $enRecepcion, $this->actor, $enRecepcion->version, 'q6:cust:1:'.$pedido->id, $almacenDestino->id,
            [['folio' => $folios[0], 'tipo' => 'caja', 'condicion' => 'bueno', 'piezas' => 2]]
        );
        $this->assertSame(ResguardoPdv::ESTADO_EN_RECEPCION, $parcial->estado);
        $this->assertSame(EstatusSucursalPedidoBma::CUSTODIA_PARCIAL, $pedido->fresh()->estatus_sucursal);
        $custodia = app(RegistrarConfirmacionCustodiaPdvService::class)->ejecutar(
            $parcial, $this->actor, $parcial->version, 'q6:cust:2:'.$pedido->id, $almacenDestino->id,
            [['folio' => $folios[1], 'tipo' => 'caja', 'condicion' => 'bueno', 'piezas' => 1]]
        );
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $custodia->estado);
        $this->assertSame(EstatusSucursalPedidoBma::EN_CUSTODIA, $pedido->fresh()->estatus_sucursal);
        $this->assertSame($almacenDestino->id, $custodia->almacen_id);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
    }

    public function test_captura_sin_id_y_guardada_comparten_clasificacion(): void
    {
        $pedido = $this->pedido(true);
        $nuevo = $pedido->replicate();
        $this->assertFalse($nuevo->exists);
        $this->assertTrue($nuevo->esTrasladoInternoAPdv());
        $this->assertTrue($nuevo->consultaCedisModoRevisionMercancia());
        $this->assertFalse($nuevo->ofreceRastreo());
        $nuevo->folio = 'Q6-NUEVO-'.uniqid();
        $nuevo->save();
        $this->assertTrue($nuevo->fresh()->esTrasladoInternoAPdv());
        $this->assertTrue($nuevo->fresh()->toArray()['es_traslado_interno_pdv']);
    }

    public function test_sucursal_comercial_y_modalidad_externa_no_eximen_peso_ni_guia(): void
    {
        $pedido = $this->pedido(true);
        $pedido->update(['solicitud_preparacion_json' => ['codigo_modalidad' => 'ENVIO_BODEGA_NORMAL']]);
        $this->assertFalse($pedido->fresh()->esTrasladoInternoAPdv());
        $this->assertFalse($pedido->fresh()->consultaCedisModoRevisionMercancia());
        $this->assertTrue($pedido->fresh()->ofreceRastreo());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('paquetería');
        app(SolicitarPesajePedidoBmaService::class)->ejecutar($pedido->fresh(), $this->actor->id);
    }

    public function test_envio_externo_desde_pdv_sin_sucursal_conserva_pesaje(): void
    {
        $pedido = $this->pedido(true);
        $paq = CatalogoPaqueteriaPedido::query()->create(['nombre' => 'Externa Q6', 'categoria' => 'comercial', 'requiere_peso' => true, 'requiere_caja' => true, 'activo' => true]);
        $pedido->update(['sucursal_destino_id' => null, 'catalogo_paqueteria_id' => $paq->id]);
        $pendiente = app(SolicitarPesajePedidoBmaService::class)->ejecutar($pedido->fresh(), $this->actor->id);
        $this->assertFalse($pendiente->consultaCedisModoRevisionMercancia());
        $this->assertTrue($pendiente->ofreceRastreo());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tipo de caja y pesos');
        $this->responder($pendiente);
    }

    public function test_transporte_guardado_no_reactiva_caja_ni_peso_del_traslado(): void
    {
        $pedido = $this->pedido(false);
        $paq = CatalogoPaqueteriaPedido::query()->create(['nombre' => 'Comercial previa', 'categoria' => 'comercial', 'requiere_peso' => true, 'requiere_caja' => true, 'activo' => true]);
        $pedido->update(['catalogo_paqueteria_id' => $paq->id, 'cliente_proporciona_guia' => true]);
        $pendiente = app(SolicitarPesajePedidoBmaService::class)->ejecutar($pedido->fresh(), $this->actor->id);
        $respuesta = $this->responder($pendiente);
        $this->assertNull($respuesta->peso_real_kg);
        $this->assertNull($respuesta->catalogo_tipo_caja_id);
        $this->assertFalse($respuesta->ofreceRastreo());
    }

    public function test_respuesta_vacia_o_sin_evidencia_no_autoriza_traslado(): void
    {
        $pedido = app(SolicitarPesajePedidoBmaService::class)->ejecutar($this->pedido(false), $this->actor->id);
        foreach ([[], ['numero_cajas' => 1, 'revisiones' => [$this->revision()]]] as $payload) {
            try {
                app(ResponderPesajePedidoBmaService::class)->ejecutar($pedido, $this->actor->id, [], $payload);
                $this->fail('Debe rechazar respuesta incompleta.');
            } catch (\InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
        $this->assertNull($pedido->fresh()->pesaje_respondido_at);
    }

    public function test_faltante_observado_bloquea_avance_sin_consultar_stock(): void
    {
        $pedido = app(SolicitarPesajePedidoBmaService::class)->ejecutar($this->pedido(true), $this->actor->id);
        $respondido = $this->responder($pedido, ['cantidad_encontrada' => 2, 'comentario' => 'Falta una pieza']);
        $this->assertTrue($respondido->tieneSinExistenciaAbierta());
        $this->assertTrue($respondido->tiene_observaciones_fisicas);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sin existencias sin atender');
        $respondido->assertSinExistenciaAtendida();
    }

    public function test_backend_revalida_recorrido_del_objeto_desactualizado(): void
    {
        $pedido = app(SolicitarPesajePedidoBmaService::class)->ejecutar($this->pedido(false), $this->actor->id);
        $this->assertTrue($pedido->consultaCedisModoRevisionMercancia());
        PedidoBma::query()->whereKey($pedido->id)->update(['sucursal_destino_id' => null]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tipo de caja y pesos');
        $this->responder($pedido);
    }

    public function test_catalogo_con_stock_cero_permite_revision_observada_sin_movimientos(): void
    {
        $pedido = $this->pedido(true);
        Almacen::query()->whereKey($pedido->almacen_id)->update(['permite_busqueda_productos' => true]);
        $productoId = DB::table('productos')->insertGetId([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'folio' => 'Q6-CAT', 'sku' => 'Q6-OBS', 'descripcion' => 'Producto catálogo', 'activo' => true,
        ]);
        DB::table('inventarios')->insert(['producto_id' => $productoId, 'almacen_id' => $pedido->almacen_id, 'existencia' => 0, 'apartado' => 0]);
        $antes = (array) DB::table('inventarios')->first();
        $pendiente = app(SolicitarPesajePedidoBmaService::class)->ejecutar($pedido, $this->actor->id);
        $respuesta = app(ResponderPesajePedidoBmaService::class)->ejecutar($pendiente, $this->actor->id, [], [
            'almacen_id' => $pedido->almacen_id, 'numero_cajas' => 1,
            'revisiones' => [array_merge($this->revision(), ['producto_id' => $productoId, 'sku' => 'Q6-OBS'])],
            'evidencias_generales' => [UploadedFile::fake()->image('observada.jpg')],
        ]);
        $this->assertSame(3, $respuesta->revisionesProducto()->sole()->cantidad_encontrada);
        $this->assertSame($antes, (array) DB::table('inventarios')->first());
    }

    public function test_cantidades_incoherentes_no_guardan_respuesta(): void
    {
        $pedido = app(SolicitarPesajePedidoBmaService::class)->ejecutar($this->pedido(false), $this->actor->id);
        foreach ([['cantidad_encontrada' => 4], ['cantidad_solicitada' => null],
            ['cantidad_encontrada' => 2, 'comentario' => ''], ['estado_fisico' => 'sin_existencia', 'cantidad_encontrada' => 1]] as $cambio) {
            try {
                $this->responder($pedido, $cambio);
                $this->fail('No debe aceptar cantidades incoherentes.');
            } catch (\InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
        $this->assertNull($pedido->fresh()->pesaje_respondido_at);
        $this->assertSame(0, $pedido->revisionesProducto()->count());
    }

    public function test_pdv_sin_sucursal_origen_no_infiere_otro_pdv(): void
    {
        $pedido = $this->pedido(true);
        $pedido->almacen->update(['sucursal_id' => null]);
        $this->assertFalse($pedido->fresh()->esTrasladoInternoAPdv());
        $this->assertTrue($pedido->fresh()->ofreceRastreo());
    }

    public function test_request_interno_acepta_revision_con_bultos_y_rechaza_cantidades_ausentes(): void
    {
        $pedido = $this->pedido(true);
        $request = new \App\Http\Requests\ControlPedidos\ResponderPesajePedidoBmaRequest;
        $route = new \Illuminate\Routing\Route('PUT', 'q6/{pedidoBma}', fn () => null);
        $route->bind(\Illuminate\Http\Request::create('/q6/'.$pedido->id, 'PUT'));
        $route->setParameter('pedidoBma', $pedido);
        $request->setRouteResolver(fn () => $route);
        $payload = ['numero_cajas' => 2, 'revisiones' => [$this->revision()]];
        $valido = \Illuminate\Support\Facades\Validator::make($payload, $request->rules());
        $this->assertTrue($valido->passes(), json_encode($valido->errors()->toArray()));
        unset($payload['revisiones'][0]['cantidad_encontrada']);
        $this->assertFalse(\Illuminate\Support\Facades\Validator::make($payload, $request->rules())->passes());
    }

    public function test_danos_siguen_exigiendo_evidencia_del_producto(): void
    {
        $pedido = app(SolicitarPesajePedidoBmaService::class)->ejecutar($this->pedido(false), $this->actor->id);
        try {
            $this->responder($pedido, ['estado_fisico' => 'danado', 'comentario' => 'Rotura']);
            $this->fail('El daño exige evidencia propia.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('requiere evidencia', $e->getMessage());
        }
        $respondido = $this->responder($pedido, ['estado_fisico' => 'danado', 'comentario' => 'Rotura',
            'evidencias' => [UploadedFile::fake()->image('rotura.jpg')]]);
        $this->assertSame('danado', $respondido->estado_fisico_general);
        $this->assertTrue($respondido->revisionesProducto()->sole()->evidencias()->exists());
    }

    public function test_consulta_inicial_usa_folio_del_pedido_sin_numero_de_remision_ni_referencia_adicional(): void
    {
        $pedido = $this->pedido(false);
        $this->assertNull($pedido->numero_remision);
        $this->assertSame(0, $pedido->referencias()->count());
        $solicitado = app(SolicitarPesajePedidoBmaService::class)->ejecutar($pedido, $this->actor->id);
        $this->assertNotNull($solicitado->pesaje_solicitado_at);
        $this->assertNull($solicitado->numero_remision);
    }

    public function test_el_pdf_no_sustituye_el_folio_del_pedido_para_solicitar(): void
    {
        $pedido = $this->pedido(false);
        $pedido->update(['folio_remision' => null]);
        $this->expectException(ValidationException::class);
        app(SolicitarPesajePedidoBmaService::class)->ejecutar($pedido->fresh(), $this->actor->id);
    }

    private function responder(PedidoBma $pedido, array $cambios = []): PedidoBma
    {
        return app(ResponderPesajePedidoBmaService::class)->ejecutar($pedido, $this->actor->id, [], [
            'numero_cajas' => 2, 'revisiones' => [array_merge($this->revision(), $cambios)],
            'evidencias_generales' => [UploadedFile::fake()->image('revision.jpg')],
        ]);
    }

    private function revision(): array
    {
        return ['descripcion_producto' => 'Pieza observada', 'cantidad_solicitada' => 3, 'cantidad_encontrada' => 3,
            'estado_fisico' => 'bueno', 'comentario' => 'Revisada físicamente', 'unica_pieza' => true, 'mejor_ejemplar' => true];
    }

    private function validar(PedidoBma $pedido): void
    {
        $validador = new class {
            use ValidacionCamposPedidoBma;
            public function validar(PedidoBma $p): void { $this->validarCamposRequeridos($p); }
        };
        $validador->validar($pedido);
    }

    private function documento(PedidoBma $pedido, string $tipo): void
    {
        $pedido->documentos()->create(['tipo' => $tipo, 'ruta_archivo' => 'q6/test.pdf', 'nombre_original' => 'test.pdf', 'mime_type' => 'application/pdf']);
    }

    private function pedido(bool $pdv): PedidoBma
    {
        $sucursal = Sucursal::factory()->create(['nombre' => 'City', 'activo' => true]);
        $almacen = Almacen::query()->create(['codigo' => $pdv ? 'PDV' : 'CEDIS-Q6', 'nombre' => $pdv ? 'Carmen' : 'CEDIS',
            'sucursal_id' => Sucursal::factory()->create(['nombre' => 'Carmen'])->id, 'activo' => true, 'permite_salida_pedidos' => true]);
        $origen = CatalogoOrigenPedido::query()->create(['codigo' => 'Q6-'.uniqid(), 'nombre' => 'Logística Q6', 'requiere_logistica' => true, 'activo' => true]);
        $lista = DB::table('catalogo_listas_descuento')->insertGetId(['nombre' => 'Q6', 'activo' => true]);
        $cliente = DB::table('clientes')->insertGetId(['numero_cliente' => uniqid(), 'nombre' => 'Cliente Q6', 'lista_actual_id' => $lista]);
        $pedido = PedidoBma::query()->create(['folio' => 'Q6-'.uniqid(), 'folio_remision' => 'REM-'.uniqid(),
            'fecha' => now()->toDateString(), 'vendedor_id' => $this->actor->id, 'cliente_id' => $cliente,
            'origen_id' => $origen->id, 'almacen_id' => $almacen->id, 'sucursal_destino_id' => $sucursal->id,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR)->id,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_COMPLETO, 'total_mercancia' => 100, 'total_a_cobrar' => 100,
            'costo_envio' => null, 'es_resguardo' => false, 'cantidad_piezas' => 3]);
        $this->documento($pedido, PedidoBmaDocumento::TIPO_PDF_PEDIDO);
        return $pedido->fresh();
    }
}
