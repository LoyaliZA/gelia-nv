<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoZonaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMotivo;
use App\Models\User;
use App\Services\ControlPedidos\CalcularTotalesEnvioPedidoService;
use App\Services\ControlPedidos\ResponderPesajePedidoBmaService;
use App\Services\SaldosAFavor\CoberturaPagoPedidoBmaService;
use App\Services\SaldosAFavor\GenerarCreditoSafService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use App\Services\SaldosAFavor\SincronizarAplicacionesPedidoSafService;
use App\Support\ControlPedidos\VisibilidadTareaPreparacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class Q8AccesoPagosSafEvidenciaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Storage::fake('public');
        $lista = CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
        $this->user = User::factory()->create();
        $this->cliente = Cliente::create([
            'numero_cliente' => '90081',
            'nombre' => 'Cliente Q8',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);
        SafMotivo::firstOrCreate(
            ['codigo' => 'pago_de_mas'],
            ['nombre' => 'De más', 'categoria' => 'diferencias_pago', 'requiere_detalle' => false, 'activo' => true, 'orden' => 1]
        );
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO],
            ['codigo_interno' => 'Q8-PESAJE', 'nombre_visual' => 'Pesaje', 'color_hex' => '#64748B', 'orden' => 4, 'activo' => true]
        );
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_EN_CEDIS],
            ['codigo_interno' => 'Q8-CEDIS', 'nombre_visual' => 'CEDIS', 'color_hex' => '#0F766E', 'orden' => 6, 'activo' => true]
        );
    }

    public static function pagosQ81(): array
    {
        $casos = [];
        foreach (['transferencia', 'efectivo', 'credito'] as $forma) {
            foreach ([false, true] as $conSaf) {
                foreach ([false, true] as $detalle) {
                    foreach ([0, 200] as $parcial) {
                        foreach ([0, 25] as $excedente) {
                            $casos["$forma-saf$conSaf-cajas$detalle-parcial$parcial-extra$excedente"] = [$forma, $conSaf, $detalle, $parcial, $excedente];
                        }
                    }
                }
            }
        }
        return $casos;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pagosQ81')]
    public function test_q81_pendiente_real_libro_saf_y_reintento(string $forma, bool $conSaf, bool $detalle, int $parcial, int $excedente): void
    {
        \App\Models\ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => 'control_pedidos.envios.detalle_cajas'],
            ['valor' => $detalle ? '1' : '0', 'tipo' => 'boolean', 'descripcion' => 'Q8.1', 'grupo' => 'control_pedidos']
        );
        app(\App\Services\ControlPedidos\EnviosPedidoBmaConfig::class)->olvidarCache();
        $zona = CatalogoZonaPedido::query()->create(['nombre' => 'Zona Q8.1', 'costo_adicional' => 150, 'activo' => true]);
        $pedido = $this->pedido(['total_mercancia' => 1000, 'costo_envio' => 250, 'total_a_cobrar' => 1250,
            'catalogo_zona_id' => $zona->id, 'pesaje_respondido_at' => now()]);
        if ($detalle) {
            $tipoId = \Illuminate\Support\Facades\DB::table('catalogo_tipos_caja_pedido')->insertGetId([
                'nombre' => 'CAJA Q8.1', 'peso_volumetrico' => 1, 'largo' => 10, 'ancho' => 10, 'alto' => 10,
                'activo' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $pedido->cajas()->create(['uuid_operativo' => (string) Str::uuid(), 'catalogo_tipo_caja_id' => $tipoId,
                'orden' => 1, 'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
                'costo_envio' => 100, 'costo_adicional' => 0, 'costo_seguro' => 0]);
        }
        $saf = app(SincronizarAplicacionesPedidoSafService::class);
        if ($conSaf) {
            $credito = app(GenerarCreditoSafService::class)->handle(['cliente_id' => $this->cliente->id,
                'monto' => 100, 'saf_motivo_id' => SafMotivo::where('codigo', 'pago_de_mas')->value('id'),
                'canal_origen' => 'bellaroma', 'generado_por_id' => $this->user->id]);
            $saf->reservarParaPedido($pedido, [['monto' => 80]], $this->user->id);
            $saf->aplicarReservasPedido($pedido->fresh(), $this->user->id);
            $saf->aplicarReservasPedido($pedido->fresh(), $this->user->id);
            $this->assertSame(1, \App\Models\SaldosAFavor\SafMovimiento::where('pedido_bma_id', $pedido->id)->where('tipo', 'aplicacion')->count());
            $this->assertSame(80.0, (float) \App\Models\SaldosAFavor\SafMovimiento::where('pedido_bma_id', $pedido->id)->where('tipo', 'aplicacion')->sum('monto'));
            $this->assertSame(20.0, (float) $credito->fresh()->monto_disponible);
        }
        if ($parcial) {
            PedidoBmaPago::create(['pedido_bma_id' => $pedido->id, 'numero_exhibicion' => 1, 'monto' => $parcial,
                'forma_pago' => 'efectivo', 'activo_para_cobertura' => true, 'capturado_por_id' => $this->user->id]);
        }
        $cobertura = app(CoberturaPagoPedidoBmaService::class);
        $antes = $cobertura->calcular($pedido->fresh());
        $pendiente = 1250 - ($conSaf ? 80 : 0) - $parcial;
        $this->assertSame((float) $pendiente, (float) $antes['pendiente']);
        $banco = $forma === 'transferencia' ? \App\Models\CatalogoBanco::create(['nombre' => 'Banco prueba Q8.1', 'activo' => true])->id : null;
        $datos = ['monto' => $pendiente + $excedente, 'forma_pago' => $forma,
            'catalogo_banco_id' => $banco, 'idempotency_key' => 'q81-pago'];
        $archivo = $forma === 'credito' ? null : UploadedFile::fake()->create('comprobante.pdf', 10, 'application/pdf');
        $servicio = app(RegistrarPagoPedidoBmaService::class);
        $pago = $servicio->handle($pedido->fresh(), $datos, $archivo, $this->user->id);
        $creditos = SafCredito::count();
        $movimientos = \App\Models\SaldosAFavor\SafMovimiento::count();
        $reintento = $servicio->handle($pedido->fresh(), $datos, $archivo, $this->user->id);
        $this->assertSame($pago->id, $reintento->id);
        $this->assertSame($creditos, SafCredito::count());
        $this->assertSame($movimientos, \App\Models\SaldosAFavor\SafMovimiento::count());
        $this->assertSame($parcial ? 2 : 1, PedidoBmaPago::where('pedido_bma_id', $pedido->id)->count());
        $despues = $cobertura->calcular($pedido->fresh());
        $this->assertSame($excedente ? 'con_excedente' : 'cubierto', $despues['cobertura']);
        $this->assertSame((float) $excedente, (float) $despues['excedente_generado']);
        $this->assertSame($conSaf ? 80.0 : 0.0, (float) $pedido->fresh()->saldo_a_favor);
        if (! $excedente) $this->assertSame($conSaf ? 1 : 0, SafCredito::count());
    }

    public function test_pago_exacto_del_pendiente_no_genera_excedente_ni_saf(): void
    {
        $pedido = $this->pedido(['total_mercancia' => 1000, 'costo_envio' => 150, 'total_a_cobrar' => 1150]);
        $pago = app(RegistrarPagoPedidoBmaService::class)->handle($pedido, [
            'monto' => 1150,
            'forma_pago' => 'credito',
            'idempotency_key' => 'q8-exacto',
        ], null, $this->user->id);

        $this->assertFalse(PedidoBmaPago::formaRequiereComprobante('credito'));
        $resumen = app(CoberturaPagoPedidoBmaService::class)->calcular($pedido->fresh());
        $this->assertSame('cubierto', $resumen['cobertura']);
        $this->assertSame(0.0, (float) $resumen['excedente_generado']);
        $this->assertSame(0, SafCredito::query()->count());
        $this->assertSame(1, PedidoBmaPago::query()->count());

        app(RegistrarPagoPedidoBmaService::class)->handle($pedido->fresh(), [
            'monto' => 1150,
            'forma_pago' => 'credito',
            'idempotency_key' => 'q8-exacto',
        ], null, $this->user->id);
        $this->assertSame($pago->id, PedidoBmaPago::query()->sole()->id);
        $this->assertSame(0, SafCredito::query()->count());
    }

    public function test_pago_parcial_previo_y_rechazado_no_inflan_el_pendiente(): void
    {
        $pedido = $this->pedido(['total_mercancia' => 1000, 'costo_envio' => 0, 'total_a_cobrar' => 1000]);
        PedidoBmaPago::query()->create([
            'pedido_bma_id' => $pedido->id, 'numero_exhibicion' => 1, 'monto' => 400,
            'forma_pago' => 'efectivo', 'activo_para_cobertura' => true, 'capturado_por_id' => $this->user->id,
        ]);
        PedidoBmaPago::query()->create([
            'pedido_bma_id' => $pedido->id, 'numero_exhibicion' => 2, 'monto' => 900,
            'forma_pago' => 'efectivo', 'activo_para_cobertura' => false, 'capturado_por_id' => $this->user->id,
            'estado_revision' => PedidoBmaPago::REVISION_RECHAZADO,
        ]);
        app(RegistrarPagoPedidoBmaService::class)->handle($pedido, [
            'monto' => 600, 'forma_pago' => 'credito', 'idempotency_key' => 'q8-resto',
        ], null, $this->user->id);

        $resumen = app(CoberturaPagoPedidoBmaService::class)->calcular($pedido->fresh());
        $this->assertSame('cubierto', $resumen['cobertura']);
        $this->assertSame(0.0, (float) $resumen['excedente_generado']);
        $this->assertSame(1000.0, (float) $resumen['total_pagado']);
    }

    public function test_excedente_real_conserva_la_generacion_vigente_de_saf(): void
    {
        $pedido = $this->pedido(['total_mercancia' => 100, 'costo_envio' => 0, 'total_a_cobrar' => 100]);
        app(RegistrarPagoPedidoBmaService::class)->handle($pedido, [
            'monto' => 130, 'forma_pago' => 'credito',
        ], null, $this->user->id);
        $resumen = app(CoberturaPagoPedidoBmaService::class)->calcular($pedido->fresh());
        $this->assertSame('con_excedente', $resumen['cobertura']);
        $this->assertSame(30.0, (float) $resumen['excedente_generado']);
    }

    public function test_detalle_de_cajas_suma_la_reexpedicion_de_zona_una_sola_vez(): void
    {
        \App\Models\ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => 'control_pedidos.envios.detalle_cajas'],
            ['valor' => '1', 'tipo' => 'boolean', 'descripcion' => 'q8', 'grupo' => 'control_pedidos']
        );
        app(\App\Services\ControlPedidos\EnviosPedidoBmaConfig::class)->olvidarCache();
        $zona = CatalogoZonaPedido::query()->firstOrCreate(
            ['nombre' => 'Con reexpedición Q8'],
            ['costo_adicional' => 150, 'activo' => true]
        );
        $pedido = $this->pedido([
            'catalogo_zona_id' => $zona->id,
            'total_mercancia' => 1000,
            'costo_envio' => 150,
            'pesaje_respondido_at' => now(),
            'pesaje_respondido_por_id' => $this->user->id,
        ]);
        $tipoId = \Illuminate\Support\Facades\DB::table('catalogo_tipos_caja_pedido')->insertGetId([
            'nombre' => 'CAJA Q8', 'peso_volumetrico' => 1, 'largo' => 10, 'ancho' => 10, 'alto' => 10,
            'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $pedido->cajas()->create([
            'uuid_operativo' => (string) Str::uuid(),
            'catalogo_tipo_caja_id' => $tipoId,
            'orden' => 1,
            'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
            'costo_envio' => 100,
            'costo_adicional' => 0,
            'costo_seguro' => 0,
        ]);

        $totales = app(CalcularTotalesEnvioPedidoService::class)->calcular($pedido->fresh(['cajas', 'zona']));
        $this->assertSame('250.00', $totales['costo_para_cobertura']);
    }

    public function test_saf_se_reserva_del_libro_y_no_acepta_mas_del_disponible(): void
    {
        $credito = app(GenerarCreditoSafService::class)->handle([
            'cliente_id' => $this->cliente->id,
            'monto' => 80,
            'saf_motivo_id' => SafMotivo::where('codigo', 'pago_de_mas')->value('id'),
            'canal_origen' => 'bellaroma',
            'generado_por_id' => $this->user->id,
        ]);
        $pedido = $this->pedido(['total_mercancia' => 200, 'costo_envio' => 0, 'total_a_cobrar' => 200]);
        $aplicado = app(SincronizarAplicacionesPedidoSafService::class)->reservarParaPedido($pedido, [
            ['saf_credito_id' => $credito->id, 'monto' => 30],
        ], $this->user->id);
        $this->assertSame(30.0, $aplicado);
        $this->assertSame(170.0, (float) $pedido->fresh()->total_a_cobrar);

        $otro = $this->pedido(['total_mercancia' => 200, 'costo_envio' => 0, 'total_a_cobrar' => 200]);
        $this->expectException(InvalidArgumentException::class);
        app(SincronizarAplicacionesPedidoSafService::class)->reservarParaPedido($otro, [
            ['monto' => 80],
        ], $this->user->id);
    }

    public function test_actualizacion_conserva_evidencia_y_marca_la_pieza_retirada(): void
    {
        $pedido = $this->pedidoConsulta();
        $primero = app(ResponderPesajePedidoBmaService::class)->ejecutar($pedido, $this->user->id, [], [
            'numero_cajas' => 1,
            'evidencias_generales' => [UploadedFile::fake()->image('lote.jpg')],
            'revisiones' => [[
                'descripcion_producto' => 'Pieza dañada',
                'sku' => 'PZ-1',
                'cantidad_solicitada' => 1,
                'cantidad_encontrada' => 1,
                'estado_fisico' => 'danado',
                'comentario' => 'Golpe',
                'unica_pieza' => true,
                'mejor_ejemplar' => true,
                'evidencias' => [UploadedFile::fake()->image('golpe.jpg')],
            ]],
        ]);
        $revision = $primero->revisionesProducto()->sole();
        $this->assertSame(1, $revision->evidencias()->count());

        $cedis = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_EN_CEDIS)->first();
        $primero->update([
            'catalogo_estatus_pedido_id' => $cedis->id,
            'consulta_actualizacion_pendiente' => false,
        ]);
        $this->assertTrue($primero->fresh()->puedeMarcarEmpacado());
        $primero->update(['consulta_actualizacion_pendiente' => true]);
        $this->assertFalse($primero->fresh()->puedeMarcarEmpacado());
        $primero->update([
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO)->value('id'),
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'consulta_actualizacion_pendiente' => true,
        ]);

        $segundo = app(ResponderPesajePedidoBmaService::class)->ejecutar($primero->fresh(), $this->user->id, [], [
            'numero_cajas' => 1,
            'revisiones' => [[
                'revision_id' => $revision->id,
                'descripcion_producto' => 'Pieza dañada',
                'sku' => 'PZ-1',
                'cantidad_solicitada' => 1,
                'cantidad_encontrada' => 1,
                'estado_fisico' => 'danado',
                'comentario' => 'Golpe',
                'unica_pieza' => true,
                'mejor_ejemplar' => true,
            ]],
        ]);
        $vigente = $segundo->revisionesProducto()->whereNull('retirada_at')->sole();
        $this->assertSame($revision->id, $vigente->id);
        $this->assertTrue($vigente->unica_pieza);
        $this->assertTrue($vigente->mejor_ejemplar);
        $this->assertSame(1, $vigente->evidencias()->count());
        $this->assertFalse($segundo->consulta_actualizacion_pendiente);

        $segundo->update([
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'consulta_actualizacion_pendiente' => true,
        ]);
        $tercero = app(ResponderPesajePedidoBmaService::class)->ejecutar($segundo->fresh(), $this->user->id, [], [
            'numero_cajas' => 1,
            'evidencias_generales' => [UploadedFile::fake()->image('lote-nuevo.jpg')],
            'revisiones' => [[
                'descripcion_producto' => 'Pieza nueva',
                'sku' => 'PZ-2',
                'cantidad_solicitada' => 1,
                'cantidad_encontrada' => 1,
                'estado_fisico' => 'bueno',
                'comentario' => 'Revisada',
            ]],
        ]);
        $retirada = PedidoBmaRevisionProducto::query()->find($revision->id);
        $this->assertNotNull($retirada->retirada_at);
        $this->assertSame(1, $retirada->evidencias()->count());
        $this->assertSame(0, $tercero->revisionesProducto()->whereNull('retirada_at')->sole()->evidencias()->count());
    }

    public function test_sustitucion_no_hereda_la_evidencia_de_la_pieza_anterior(): void
    {
        $pedido = $this->pedidoConsulta();
        $primero = app(ResponderPesajePedidoBmaService::class)->ejecutar($pedido, $this->user->id, [], [
            'numero_cajas' => 1,
            'evidencias_generales' => [UploadedFile::fake()->image('lote.jpg')],
            'revisiones' => [[
                'descripcion_producto' => 'Pieza dañada',
                'sku' => 'PZ-1',
                'estado_fisico' => 'danado',
                'comentario' => 'Golpe',
                'evidencias' => [UploadedFile::fake()->image('golpe.jpg')],
            ]],
        ]);
        $vieja = $primero->revisionesProducto()->sole();
        $primero->update(['estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE]);
        $segundo = app(ResponderPesajePedidoBmaService::class)->ejecutar($primero->fresh(), $this->user->id, [], [
            'numero_cajas' => 1,
            'evidencias_generales' => [UploadedFile::fake()->image('lote-2.jpg')],
            'revisiones' => [[
                'revision_id' => $vieja->id,
                'descripcion_producto' => 'Otra pieza',
                'sku' => 'PZ-9',
                'estado_fisico' => 'danado',
                'comentario' => 'Otro daño',
                'evidencias' => [UploadedFile::fake()->image('otro.jpg')],
            ]],
        ]);
        $this->assertNotNull($vieja->fresh()->retirada_at);
        $nueva = $segundo->revisionesProducto()->whereNull('retirada_at')->sole();
        $this->assertNotSame($vieja->id, $nueva->id);
        $this->assertSame(1, $nueva->evidencias()->count());
        $this->assertSame(1, $vieja->fresh()->evidencias()->count());
    }

    public function test_cambiar_cantidad_conserva_la_foto_del_producto_y_pide_foto_nueva_del_lote(): void
    {
        $pedido = $this->pedidoConsulta();
        $primero = app(ResponderPesajePedidoBmaService::class)->ejecutar($pedido, $this->user->id, [], [
            'numero_cajas' => 1,
            'evidencias_generales' => [UploadedFile::fake()->image('lote.jpg')],
            'revisiones' => [[
                'descripcion_producto' => 'Pieza dañada',
                'sku' => 'PZ-1',
                'cantidad_solicitada' => 2,
                'cantidad_encontrada' => 2,
                'estado_fisico' => 'danado',
                'comentario' => 'Golpe',
                'evidencias' => [UploadedFile::fake()->image('golpe.jpg')],
            ]],
        ]);
        $revision = $primero->revisionesProducto()->sole();
        $primero->update(['estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE]);
        try {
            app(ResponderPesajePedidoBmaService::class)->ejecutar($primero->fresh(), $this->user->id, [], [
                'numero_cajas' => 1,
                'revisiones' => [[
                    'revision_id' => $revision->id,
                    'descripcion_producto' => 'Pieza dañada',
                    'sku' => 'PZ-1',
                    'cantidad_solicitada' => 2,
                    'cantidad_encontrada' => 1,
                    'estado_fisico' => 'danado',
                    'comentario' => 'Falta una',
                ]],
            ]);
            $this->fail('El cambio de cantidad exige una foto nueva del lote.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('foto', $e->getMessage());
        }
        $segundo = app(ResponderPesajePedidoBmaService::class)->ejecutar($primero->fresh(), $this->user->id, [], [
            'numero_cajas' => 1,
            'evidencias_generales' => [UploadedFile::fake()->image('lote-nuevo.jpg')],
            'revisiones' => [[
                'revision_id' => $revision->id,
                'descripcion_producto' => 'Pieza dañada',
                'sku' => 'PZ-1',
                'cantidad_solicitada' => 2,
                'cantidad_encontrada' => 1,
                'estado_fisico' => 'danado',
                'comentario' => 'Falta una',
            ]],
        ]);
        $this->assertSame(1, $revision->fresh()->evidencias()->count());
        $this->assertSame(1, $segundo->revisionesProducto()->whereNull('retirada_at')->count());
    }

    public function test_preparacion_informa_cuando_falta_el_esquema_de_abastecimiento(): void
    {
        $pedido = $this->pedido();
        $modalidad = CatalogoModalidadPreparacionPedido::query()->firstOrCreate(
            ['codigo' => CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA],
            ['nombre' => 'Recoge en tienda', 'activo' => true]
        );
        $almacen = Almacen::query()->create(['codigo' => 'Q8-T', 'nombre' => 'Tienda Q8']);
        $tarea = $pedido->tareasPreparacion()->create([
            'almacen_id' => $almacen->id,
            'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'estado' => 'EN_ATENCION',
            'version' => 1,
            'area_responsable_codigo' => 'TIENDA',
        ]);
        \Illuminate\Support\Facades\Schema::partialMock()
            ->shouldReceive('hasColumn')->andReturn(false)
            ->shouldReceive('hasTable')->andReturn(false);

        $payload = VisibilidadTareaPreparacion::payloadTienda($tarea->fresh(), $this->user);
        $this->assertFalse($payload['abastecimiento_esquema']);
        $this->assertFalse($payload['abastecimiento_pendiente']);
        $this->assertNotSame('', (string) $payload['abastecimiento_aviso']);
    }

    private function pedido(array $extra = []): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR],
            ['codigo_interno' => 'Q8-BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#94A3B8', 'orden' => 1, 'activo' => true]
        );

        return PedidoBma::query()->create(array_merge([
            'folio' => 'Q8-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->user->id,
            'cliente_id' => $this->cliente->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 0,
            'costo_envio' => 0,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'saldo_a_favor' => 0,
            'total_a_cobrar' => 0,
        ], $extra));
    }

    private function pedidoConsulta(): PedidoBma
    {
        $paq = CatalogoPaqueteriaPedido::query()->create([
            'nombre' => 'Local Q8',
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL,
            'requiere_peso' => false,
            'requiere_caja' => false,
            'activo' => true,
        ]);

        return $this->pedido([
            'catalogo_paqueteria_id' => $paq->id,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO)->value('id'),
        ]);
    }
}
