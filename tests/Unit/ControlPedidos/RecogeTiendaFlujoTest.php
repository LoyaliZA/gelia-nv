<?php

namespace Tests\Unit\ControlPedidos;

use App\Http\Requests\ControlPedidos\GuardarApartadoRecogeHoyRequest;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Services\ControlPedidos\ConfirmarApartadoRecogeHoyService;
use App\Services\ControlPedidos\EvaluarVencimientoApartadoFisicoService;
use App\Services\ControlPedidos\GenerarCaratulaRecogeHoyService;
use App\Services\ControlPedidos\GuardarApartadoRecogeHoyService;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use App\Services\ControlPedidos\ReabrirApartadoRecogeHoyService;
use App\Services\ControlPedidos\ReportarIncidenciaPreparacionService;
use App\Support\ControlPedidos\RespuestaPreparacionTienda;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RecogeTiendaFlujoTest extends TestCase
{
    private User $auxiliar;

    private PedidoBmaTareaPreparacion $tarea;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->travelTo(now()->setDate(2026, 10, 10)->setTime(12, 0));
        $tablas = [
            'solicitudes_traspasos' => ['tarea_receptora_id', 'proposito', 'deleted_at'],
            'solicitud_traspaso_productos' => ['tarea_producto_receptor_id'],
            'users' => ['name', 'deleted_at'],
            'productos' => ['sku', 'descripcion', 'activo', 'es_demo'],
            'inventarios' => ['producto_id', 'almacen_id', 'existencia'],
            'pedido_bma_tarea_documentos' => ['pedido_bma_tarea_preparacion_id', 'pedido_bma_tarea_producto_id', 'tipo_evidencia', 'ruta_interna', 'nombre_original', 'mime_type', 'tamano_bytes', 'hash_sha256', 'subido_por_id', 'subido_at', 'inmutable', 'version'],
            'pedido_bma_documentos' => ['pedido_bma_id', 'tipo', 'ruta_archivo', 'nombre_original', 'mime_type', 'tamano_bytes', 'activo', 'comentario'],
            'catalogo_modalidades_preparacion_pedido' => ['codigo', 'nombre'],
            'pedidos_bma' => ['estatus_envio', 'pesaje_respondido_at', 'pesaje_respondido_por_id', 'consulta_cerrada_at', 'consulta_actualizacion_pendiente', 'cantidad_piezas', 'cliente_id', 'vendedor_id', 'catalogo_estatus_pedido_id', 'deleted_at'],
            'pedido_bma_tareas_preparacion' => ['pedido_bma_id', 'catalogo_modalidad_preparacion_id', 'almacen_id', 'estado', 'version', 'fecha_limite', 'atendida_por_id', 'atendida_at', 'deleted_at', 'asignada_a_id', 'observaciones_solicitud', 'requiere_traslado_cedis'],
            'pedido_bma_tarea_productos' => ['pedido_bma_tarea_preparacion_id', 'pedido_bma_origen_id', 'producto_id', 'sku', 'descripcion_snapshot', 'cantidad_solicitada', 'cantidad_encontrada', 'estado_fisico', 'observacion', 'unica_pieza', 'mejor_ejemplar', 'orden'],
            'pedido_bma_cumplimiento_fisico' => ['pedido_bma_tarea_preparacion_id', 'estado', 'cantidad', 'ubicacion', 'version', 'vence_at', 'pago_confirmado_at', 'salida_autorizada_at'],
            'pedido_bma_cumplimiento_eventos' => ['pedido_bma_cumplimiento_fisico_id', 'pedido_bma_tarea_preparacion_id', 'tipo', 'usuario_id', 'motivo', 'idempotencia_clave', 'datos'],
            'pedido_bma_tarea_historial' => ['pedido_bma_tarea_preparacion_id', 'usuario_id', 'estado_anterior', 'estado_nuevo', 'accion', 'comentario', 'meta_json'],
        ];
        foreach ($tablas as $tabla => $columnas) {
            Schema::create($tabla, function (Blueprint $t) use ($columnas) {
                $t->id();
                foreach ($columnas as $columna) {
                    $t->text($columna)->nullable();
                }
                $t->timestamps();
            });
        }
        $config = Mockery::mock(PreparacionTiendaConfig::class)->makePartial();
        $config->shouldReceive('zonaHoraria')->andReturn('America/Mexico_City');
        $config->shouldReceive('cierreOperativoHora')->andReturn('20:00');
        $this->app->instance(PreparacionTiendaConfig::class, $config);
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        $this->mock(GenerarCaratulaRecogeHoyService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes()->andReturn(new PedidoBmaTareaDocumento);
        $this->auxiliar = Mockery::mock(User::class)->makePartial();
        $this->auxiliar->id = 10;
        $this->auxiliar->shouldReceive('can')->andReturn(true);
        $modalidad = CatalogoModalidadPreparacionPedido::create(['codigo' => 'RECOGE_TIENDA', 'nombre' => 'Recoge en tienda']);
        $pedido = PedidoBma::create(['cantidad_piezas' => 0]);
        $this->tarea = PedidoBmaTareaPreparacion::create(['pedido_bma_id' => $pedido->id, 'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => 1, 'asignada_a_id' => 10, 'estado' => 'EN_ATENCION', 'version' => 1, 'fecha_limite' => now()->addHours(4)]);
        PedidoBmaCumplimientoFisico::create(['pedido_bma_tarea_preparacion_id' => $this->tarea->id, 'estado' => 'POR_SEPARAR', 'cantidad' => 0, 'version' => 1]);
    }

    protected function tearDown(): void
    {
        Schema::dropAllTables();
        parent::tearDown();
    }

    private function datos(int $cantidad = 3): array
    {
        return ['version' => 1, 'ubicacion' => 'Anaquel A', 'lineas' => [[
            'sku' => 'SKU-1', 'descripcion' => 'Crema', 'cantidad' => $cantidad, 'estado_fisico' => 'bueno', 'observacion' => $cantidad === 0 ? 'Sin disponibilidad' : '',
        ]]];
    }

    public function test_confirma_sin_ubicacion_ni_anaquel(): void
    {
        $datos = $this->datos();
        unset($datos['ubicacion']);

        $apartado = app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $datos, true);

        $this->assertSame('APARTADO_CONFIRMADO', $apartado->estado);
        $this->assertNull($apartado->ubicacion);
        $this->assertSame('RESPONDIDA', $this->tarea->fresh()->estado);
    }

    public function test_separa_cumplimiento_sin_ubicacion(): void
    {
        $apartado = app(\App\Services\ControlPedidos\SepararCumplimientoFisicoService::class)
            ->ejecutar($this->tarea, $this->auxiliar, 2, null, 1);

        $this->assertSame('SEPARADA', $apartado->estado);
        $this->assertNull($apartado->ubicacion);
        $this->assertSame(2, $apartado->cantidad);
    }

    public function test_confirma_en_una_accion_y_calcula_total_sin_pago(): void
    {
        $apartado = app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $this->datos(), true);
        $this->assertSame('APARTADO_CONFIRMADO', $apartado->estado);
        $this->assertSame(3, $apartado->cantidad);
        $this->assertSame('Anaquel A', $apartado->ubicacion);
        $this->assertSame('RESPONDIDA', $this->tarea->fresh()->estado);
        $this->assertSame('apartado_confirmado', $this->tarea->pedido->fresh()->estatus_envio);
        $this->assertSame(3, $this->tarea->pedido->fresh()->cantidad_piezas);
    }

    public function test_guarda_borrador_sin_confirmar(): void
    {
        $apartado = app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $this->datos());
        $this->assertSame('POR_SEPARAR', $apartado->estado);
        $this->assertSame('EN_ATENCION', $this->tarea->fresh()->estado);
        $this->assertSame(3, $apartado->cantidad);
        $this->assertSame(2, $apartado->version);
    }

    public function test_cero_piezas_revierte_toda_la_confirmacion(): void
    {
        try {
            app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $this->datos(0), true);
            $this->fail('No debe confirmar cero piezas.');
        } catch (ValidationException $e) {
            $this->assertSame(0, $this->tarea->productos()->count());
            $this->assertSame('EN_ATENCION', $this->tarea->fresh()->estado);
            $this->assertSame(1, PedidoBmaCumplimientoFisico::first()->version);
        }
    }

    public function test_version_obsoleta_no_modifica_piezas(): void
    {
        $datos = $this->datos();
        $datos['version'] = 99;
        try {
            app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $datos);
            $this->fail('Debe rechazar la versión obsoleta.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('version', $e->errors());
            $this->assertSame(0, $this->tarea->productos()->count());
        }
    }

    public function test_una_observacion_sin_producto_no_confirma_apartado(): void
    {
        $this->tarea->productos()->create(['descripcion_snapshot' => 'Piezas del pedido', 'cantidad_encontrada' => 0, 'estado_fisico' => 'regular', 'observacion' => 'No disponible']);
        $this->expectException(ValidationException::class);
        app(ConfirmarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, 1);
    }

    public function test_guarda_marcas_y_comentario_de_pieza_sin_existencias(): void
    {
        $datos = $this->datos();
        $datos['lineas'][] = [
            'sku' => 'SKU-2',
            'descripcion' => 'Perfume',
            'cantidad' => 0,
            'estado_fisico' => 'sin_existencia',
            'observacion' => 'Sin existencia en anaquel',
            'unica_pieza' => true,
            'mejor_ejemplar' => true,
        ];

        app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $datos);

        $pieza = $this->tarea->productos()->where('sku', 'SKU-2')->first();
        $this->assertSame('sin_existencia', $pieza->estado_fisico);
        $this->assertSame('Sin existencia en anaquel', $pieza->observacion);
        $this->assertTrue($pieza->unica_pieza);
        $this->assertTrue($pieza->mejor_ejemplar);
        $respuesta = RespuestaPreparacionTienda::datos($this->tarea->fresh());
        $publicada = collect($respuesta['productos'])->firstWhere('sku', 'SKU-2');
        $this->assertTrue($publicada['unica_pieza']);
        $this->assertTrue($publicada['mejor_ejemplar']);
        $this->assertSame('Sin existencia en anaquel', $publicada['observacion']);
    }

    public function test_faltante_impide_confirmar_hasta_resolverse(): void
    {
        $datos = $this->datos();
        $datos['lineas'][] = ['sku' => 'SKU-2', 'descripcion' => 'Perfume', 'cantidad' => 0, 'estado_fisico' => 'sin_existencia', 'observacion' => 'Ventas debe resolver'];
        $this->expectException(ValidationException::class);
        app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $datos, true);
    }

    public function test_reabrir_con_complemento_conserva_piezas_y_permite_reconfirmar(): void
    {
        Storage::fake('public');
        DB::table('users')->insert(['id' => 10, 'name' => 'Ventas']);
        $this->tarea->pedido->update(['vendedor_id' => 10]);
        $apartado = app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $this->datos(), true);
        $producto = $this->tarea->productos()->first();
        app(ReabrirApartadoRecogeHoyService::class)->ejecutar(
            $this->tarea->fresh(), $this->auxiliar, 'Agregar una crema', 2,
            UploadedFile::fake()->create('complemento.pdf', 10, 'application/pdf')
        );
        $this->assertSame('PENDIENTE', $this->tarea->fresh()->estado);
        $this->assertSame(3, $this->tarea->productos()->first()->cantidad_encontrada);
        $this->assertSame('SEPARADA', $apartado->fresh()->estado);
        $this->assertSame(1, $this->tarea->pedido->documentos()->count());
        $this->assertSame('reabrir_apartado', $this->tarea->historial()->latest('id')->first()->accion);
        $this->tarea->refresh()->update(['estado' => 'EN_ATENCION', 'asignada_a_id' => 10]);
        $datos = $this->datos(4);
        $datos['version'] = $apartado->fresh()->version;
        $datos['lineas'][0]['id'] = $producto->id;
        $confirmado = app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $datos, true);
        $this->assertSame('APARTADO_CONFIRMADO', $confirmado->estado);
        $this->assertSame(4, $confirmado->cantidad);
        $this->assertSame(2, PedidoBmaCumplimientoEvento::where('tipo', 'APARTADO_CONFIRMADO')->count());
    }

    public function test_reabrir_exige_que_la_solicitud_sea_de_la_vendedora(): void
    {
        $this->expectException(HttpException::class);
        app(ReabrirApartadoRecogeHoyService::class)->ejecutar(
            $this->tarea, $this->auxiliar, 'Cambiar pedido ajeno', 1
        );
    }

    public function test_vencimiento_avisa_una_vez_sin_liberar_el_apartado(): void
    {
        $apartado = app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $this->datos(), true);
        $this->travelTo(now()->addDay());
        $evaluar = app(EvaluarVencimientoApartadoFisicoService::class);
        $this->assertSame(1, $evaluar->ejecutar());
        $this->assertSame(0, $evaluar->ejecutar());
        $this->assertSame('APARTADO_CONFIRMADO', $apartado->fresh()->estado);
        $this->assertSame(3, $apartado->fresh()->cantidad);
    }

    public function test_una_pieza_ajena_no_se_puede_modificar(): void
    {
        $datos = $this->datos();
        $datos['lineas'][0]['id'] = 999;
        $this->expectException(ValidationException::class);
        app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $datos);
    }

    public function test_confirmacion_con_ubicacion_vacia_guarda_las_piezas(): void
    {
        $datos = $this->datos();
        $datos['ubicacion'] = '';

        $apartado = app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $datos, true);

        $this->assertSame('APARTADO_CONFIRMADO', $apartado->estado);
        $this->assertNull($apartado->ubicacion);
        $this->assertSame(3, $apartado->cantidad);
        $this->assertSame(1, $this->tarea->productos()->count());
    }

    public function test_otro_auxiliar_no_puede_confirmar_una_tarea_tomada(): void
    {
        $this->tarea->update(['asignada_a_id' => 99]);
        $this->expectException(ValidationException::class);
        app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $this->datos(), true);
    }

    public function test_no_se_confirma_una_solicitud_del_dia_anterior(): void
    {
        $this->travelTo(now()->addDay());
        $this->expectException(ValidationException::class);
        app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $this->datos(), true);
    }

    public function test_usa_catalogo_del_almacen_y_permite_preparar_con_existencia_cero(): void
    {
        DB::table('productos')->insert(['id' => 71, 'sku' => 'PDV-71', 'descripcion' => 'Crema PDV', 'activo' => 1, 'es_demo' => 0]);
        DB::table('inventarios')->insert(['producto_id' => 71, 'almacen_id' => 1, 'existencia' => 0]);
        $datos = $this->datos(5);
        $datos['lineas'][0]['producto_id'] = 71;
        $datos['lineas'][0]['sku'] = 'Dato incorrecto del navegador';
        app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $datos, true);
        $this->assertSame('PDV-71', $this->tarea->productos()->first()->sku);
        $this->assertSame(5, $this->tarea->productos()->first()->cantidad_encontrada);
    }

    public function test_rechaza_productos_de_otro_almacen_sin_alterar_el_apartado(): void
    {
        DB::table('productos')->insert(['id' => 72, 'sku' => 'CEDIS-72', 'descripcion' => 'Crema CEDIS', 'activo' => 1, 'es_demo' => 0]);
        DB::table('inventarios')->insert(['producto_id' => 72, 'almacen_id' => 2, 'existencia' => 100]);
        $datos = $this->datos();
        $datos['lineas'][0]['producto_id'] = 72;
        try {
            app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $datos);
            $this->fail('El almacén de la tarea debe limitar el catálogo.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lineas', $e->errors());
            $this->assertSame(0, $this->tarea->productos()->count());
        }
    }

    public function test_conserva_fotos_por_producto_y_limpia_archivos_si_no_se_confirma(): void
    {
        Storage::fake('public');
        $datos = $this->datos(0);
        $datos['lineas'][0]['evidencias'] = [UploadedFile::fake()->create('detalle.pdf', 10, 'application/pdf')];
        try {
            app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $datos, true);
            $this->fail('No debe confirmar sin piezas.');
        } catch (ValidationException $e) {
            $this->assertCount(0, Storage::disk('public')->allFiles());
            $this->assertSame(0, $this->tarea->documentos()->count());
        }
        $datos['lineas'][0]['cantidad'] = 3;
        app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $datos, true);
        $doc = $this->tarea->documentos()->first();
        $this->assertSame($this->tarea->productos()->first()->id, (int) $doc->pedido_bma_tarea_producto_id);
        Storage::disk('public')->assertExists($doc->ruta_interna);
        $this->assertNull($this->tarea->pedido->fresh()->pesaje_respondido_at);
        DB::table('users')->insert(['id' => 10, 'name' => 'Auxiliar PDV']);
        $respuesta = RespuestaPreparacionTienda::datos($this->tarea->fresh());
        $this->assertTrue($respuesta['respondida']);
        $this->assertSame('Auxiliar PDV', $respuesta['responsable']);
        $this->assertSame(3, $respuesta['cantidad']);
        $this->assertCount(1, $respuesta['documentos']);
    }

    public function test_reporta_problema_sin_fabricar_productos(): void
    {
        $this->mock(ReportarIncidenciaPreparacionService::class)
            ->shouldReceive('ejecutar')->once()->withArgs(fn ($tarea, $usuario, $tipo, $motivo) => $tarea->id === $this->tarea->id && $motivo === 'No hay piezas disponibles')->andReturn($this->tarea);
        $datos = ['version' => 1, 'lineas' => [], 'motivo_incidencia' => 'No hay piezas disponibles'];
        $request = GuardarApartadoRecogeHoyRequest::create('/', 'POST', $datos);
        $this->assertTrue(Validator::make($datos, $request->rules())->passes());
        app(GuardarApartadoRecogeHoyService::class)->ejecutar($this->tarea, $this->auxiliar, $datos);
        $this->assertSame(0, $this->tarea->productos()->count());
        $this->assertSame(0, PedidoBmaCumplimientoFisico::first()->cantidad);
    }

    public function test_transferencia_respondida_acepta_anexo_y_regresa_a_tienda(): void
    {
        Storage::fake('public');
        $this->prepararTransferenciaRespondida();
        $notificar = Mockery::mock(NotificarPedidoBmaService::class);
        $notificar->shouldReceive('ejecutar')->once()->withArgs(function ($pedido, $tipo, $mensaje, $permisos) {
            return $tipo === 'pedido_preparacion_tienda_corregida'
                && str_contains($mensaje, 'anexo de piezas')
                && $permisos === ['control_pedidos.tienda.ver']
                && ! str_contains($mensaje, 'CEDIS');
        });
        $this->app->instance(NotificarPedidoBmaService::class, $notificar);

        app(ReabrirApartadoRecogeHoyService::class)->ejecutar(
            $this->tarea->fresh(),
            $this->auxiliar,
            'Agregar un labial',
            2,
            UploadedFile::fake()->create('anexo.pdf', 10, 'application/pdf')
        );

        $tarea = $this->tarea->fresh();
        $pedido = $tarea->pedido->fresh();
        $this->assertSame('PENDIENTE', $tarea->estado);
        $this->assertNull($tarea->asignada_a_id);
        $this->assertSame(3, $tarea->productos()->first()->cantidad_encontrada);
        $this->assertSame('anexo_piezas', $pedido->documentos()->first()->tipo);
        $this->assertNull($pedido->pesaje_respondido_at);
        $this->assertNull($pedido->pesaje_respondido_por_id);
        $this->assertSame(PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE, $pedido->estatus_envio);
    }

    public function test_transferencia_no_reabre_con_consulta_cerrada_pago_o_traslado(): void
    {
        Storage::fake('public');
        $archivo = UploadedFile::fake()->create('anexo.pdf', 10, 'application/pdf');
        $this->prepararTransferenciaRespondida(['consulta_cerrada_at' => now()]);
        $this->expectException(ValidationException::class);
        app(ReabrirApartadoRecogeHoyService::class)->ejecutar($this->tarea->fresh(), $this->auxiliar, 'Anexo', 2, $archivo);
    }

    public function test_transferencia_no_reabre_si_el_pago_ya_fue_confirmado(): void
    {
        Storage::fake('public');
        $this->prepararTransferenciaRespondida([], [], ['pago_confirmado_at' => now()]);
        $this->expectException(ValidationException::class);
        app(ReabrirApartadoRecogeHoyService::class)->ejecutar(
            $this->tarea->fresh(), $this->auxiliar, 'Anexo', 2,
            UploadedFile::fake()->create('anexo.pdf', 10, 'application/pdf')
        );
    }

    public function test_transferencia_no_reabre_si_hay_traslado_a_cedis(): void
    {
        Storage::fake('public');
        $this->prepararTransferenciaRespondida([], ['requiere_traslado_cedis' => true]);
        $this->expectException(ValidationException::class);
        app(ReabrirApartadoRecogeHoyService::class)->ejecutar(
            $this->tarea->fresh(), $this->auxiliar, 'Anexo', 2,
            UploadedFile::fake()->create('anexo.pdf', 10, 'application/pdf')
        );
    }

    public function test_credito_no_admite_anexo_de_piezas_por_esta_via(): void
    {
        Storage::fake('public');
        $this->prepararTransferenciaRespondida();
        $credito = CatalogoModalidadPreparacionPedido::create(['codigo' => 'RECOGE_TIENDA_CREDITO', 'nombre' => 'Crédito']);
        $this->tarea->update(['catalogo_modalidad_preparacion_id' => $credito->id]);
        $this->expectException(ValidationException::class);
        app(ReabrirApartadoRecogeHoyService::class)->ejecutar(
            $this->tarea->fresh(), $this->auxiliar, 'Anexo', 2,
            UploadedFile::fake()->create('anexo.pdf', 10, 'application/pdf')
        );
    }

    private function prepararTransferenciaRespondida(array $extraPedido = [], array $extraTarea = [], array $extraApartado = []): void
    {
        $modalidad = CatalogoModalidadPreparacionPedido::firstOrCreate(
            ['codigo' => 'RECOGE_TIENDA_TRANSFERENCIA'],
            ['nombre' => 'Transferencia']
        );
        if (! DB::table('users')->where('id', 10)->exists()) {
            DB::table('users')->insert(['id' => 10, 'name' => 'Ventas']);
        }
        $this->tarea->pedido->update(array_merge([
            'vendedor_id' => 10,
            'pesaje_respondido_at' => now(),
            'pesaje_respondido_por_id' => 9,
        ], $extraPedido));
        $this->tarea->update(array_merge([
            'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'estado' => 'RESPONDIDA',
            'version' => 2,
            'fecha_limite' => null,
            'requiere_traslado_cedis' => false,
        ], $extraTarea));
        $this->tarea->productos()->create([
            'sku' => 'SKU-1',
            'descripcion_snapshot' => 'Crema',
            'cantidad_solicitada' => 3,
            'cantidad_encontrada' => 3,
            'estado_fisico' => 'bueno',
            'orden' => 1,
        ]);
        $this->tarea->cumplimientoFisico()->update(array_merge([
            'estado' => 'SEPARADA',
            'cantidad' => 3,
        ], $extraApartado));
    }
}
