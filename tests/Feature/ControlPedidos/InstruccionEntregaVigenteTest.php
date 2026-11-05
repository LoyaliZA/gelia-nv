<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaHistorial;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\ActualizarPedidoBmaService;
use App\Services\ControlPedidos\AsegurarCumplimientoFisicoService;
use App\Services\ControlPedidos\AsignarGuiaPedidoBmaService;
use App\Services\ControlPedidos\AsignarSucursalDestinoPedidoService;
use App\Services\ControlPedidos\CambiarModalidadPreparacionService;
use App\Services\ControlPedidos\ConfirmarDespachoMunicipioService;
use App\Services\ControlPedidos\CrearTareaPreparacionService;
use App\Services\ControlPedidos\EnviarPedidoBmaService;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use App\Services\ControlPedidos\ResolverSegundoTrasladoTiendaService;
use App\Services\ControlPedidos\SolicitarSegundoTrasladoTiendaService;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstruccionEntregaVigenteTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.preparacion.solicitar', 'web'));
        $this->habilitarPreparacion(['ENVIO_MUNICIPIO', 'RECOGE_TIENDA', 'ENVIO_BODEGA_NORMAL']);
        foreach ([
            CatalogoEstatusPedido::FASE_BORRADOR => 'BORRADOR',
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR => 'AZUL_1',
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA => 'NARANJA',
            CatalogoEstatusPedido::FASE_ENVIADO => 'ENVIADO',
        ] as $fase => $codigo) {
            CatalogoEstatusPedido::query()->firstOrCreate(
                ['fase_ciclo' => $fase],
                ['codigo_interno' => $codigo, 'nombre_visual' => $fase, 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
            );
        }
        foreach ([
            CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO => 'Municipio',
            CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA => 'Recoge en tienda',
            CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_BODEGA_NORMAL => 'Bodega',
        ] as $codigo => $nombre) {
            CatalogoModalidadPreparacionPedido::query()->firstOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'activo' => true]
            );
        }
    }

    public function test_autoguardar_no_produce_efectos_operativos(): void
    {
        $pedido = $this->pedido();

        $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', [
            'pedido_id' => $pedido->id,
            'revision_esperada' => (int) $pedido->fresh()->revision_registro,
            'base_captura' => $pedido->fresh()->base_captura,
            'comentarios_drive' => 'Nota de borrador',
            'solicitud_preparacion' => [
                'version' => 1,
                'codigo_modalidad' => 'ENVIO_MUNICIPIO',
            ],
        ])->assertOk();

        $fresco = $pedido->fresh();
        $this->assertSame(CatalogoEstatusPedido::FASE_BORRADOR, $fresco->estatus->fase_ciclo);
        $this->assertNull($fresco->pago_validado_at);
        $this->assertSame('ENVIO_MUNICIPIO', $fresco->solicitud_preparacion_json['codigo_modalidad'] ?? null);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
        $this->assertSame(0, PedidoBmaDocumento::query()->where('tipo', PedidoBmaDocumento::TIPO_REMISION)->count());
        $this->assertSame(0, PedidoBmaPago::query()->count());
        $this->assertSame(0, ResguardoPdv::query()->count());
    }

    public function test_sustitucion_conserva_pago_remision_y_ubicacion(): void
    {
        $pedido = $this->pedido();
        $remision = $this->remision($pedido);
        $pago = $this->pago($pedido);
        $pedido->update([
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->usuario->id,
        ]);
        $tarea = $this->tarea($pedido, CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO);
        $cumplimiento = $this->cumplimiento($tarea, PedidoBmaCumplimientoFisico::ESTADO_SEPARADA, 'Anaquel A-1');

        $nueva = $this->sustituir($tarea, CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA, 'Cambio de recorrido');

        $this->assertSame(PedidoBmaTareaPreparacion::ESTADO_SUSTITUIDA, $tarea->fresh()->estado);
        $this->assertSame($tarea->id, $nueva->tarea_anterior_id);
        $this->assertSame(1, $pedido->instruccionEntregaVigente()->count());
        $this->assertSame($nueva->id, $pedido->instruccionEntregaVigente()->first()->id);
        $this->assertSame($remision->id, $pedido->remision()->first()->id);
        $this->assertSame($pago->id, $pedido->pagosExhibicion()->first()->id);
        $this->assertNotNull($pedido->fresh()->pago_validado_at);
        $fisico = $nueva->cumplimientoFisico;
        $this->assertSame($cumplimiento->id, $fisico->id);
        $this->assertSame(PedidoBmaCumplimientoFisico::ESTADO_SEPARADA, $fisico->estado);
        $this->assertSame('Anaquel A-1', $fisico->ubicacion);
        $this->assertSame(0, PedidoBmaCumplimientoFisico::query()
            ->where('estado', PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE)
            ->count());
        $historial = PedidoBmaTareaHistorial::query()
            ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
            ->where('accion', 'sustituir_instruccion')
            ->first();
        $this->assertNotNull($historial);
        $this->assertSame($this->usuario->id, $historial->usuario_id);
        $this->assertSame($nueva->id, $historial->meta_json['instruccion_nueva_id'] ?? null);
        $this->assertSame(1, PedidoBma::query()->count());
    }

    public function test_instruccion_anterior_rechaza_despacho_desde_sesion_vieja(): void
    {
        $pedido = $this->pedido();
        $tarea = $this->tarea($pedido, CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO);
        $this->cumplimiento($tarea, PedidoBmaCumplimientoFisico::ESTADO_SEPARADA, 'Mesa 2');
        $nueva = $this->sustituir($tarea, CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA, 'Ya no es municipal');

        try {
            app(ConfirmarDespachoMunicipioService::class)->ejecutar($tarea->fresh(), $this->usuario, 'Transporte');
            $this->fail('La instrucción anterior no debe despacharse.');
        } catch (ValidationException $e) {
            $this->assertSame(InstruccionEntrega::MENSAJE_OBSOLETA, $e->errors()['instruccion'][0]);
        }

        $this->assertSame(PedidoBmaCumplimientoFisico::ESTADO_SEPARADA, $nueva->fresh()->cumplimientoFisico->estado);
        $this->assertSame('Mesa 2', $nueva->fresh()->cumplimientoFisico->ubicacion);
    }

    public function test_reenvio_a_auxiliar_conserva_remision_y_pago_comprobado(): void
    {
        config(['control_pedidos.direcciones_normalizadas' => false]);
        $listaId = DB::table('catalogo_listas_descuento')->insertGetId([
            'nombre' => 'Lista fase 1',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cliente = Cliente::query()->create([
            'numero_cliente' => '9101',
            'nombre' => 'Cliente de prueba',
            'lista_actual_id' => $listaId,
            'vendedor_id' => $this->usuario->id,
        ]);
        $pedido = $this->pedido([
            'cliente_id' => $cliente->id,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA)->id,
            'folio_remision' => 'REM-FASE1',
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'total_a_cobrar' => 100,
            'pesaje_respondido_at' => now(),
            'consulta_cerrada_at' => now(),
            'origen_id' => CatalogoOrigenPedido::query()->create([
                'codigo' => 'MOST-'.uniqid(),
                'nombre' => 'Mostrador '.uniqid(),
                'requiere_logistica' => false,
                'activo' => true,
            ])->id,
        ]);
        $this->documento($pedido, PedidoBmaDocumento::TIPO_PDF_PEDIDO);
        $remision = $this->remision($pedido);
        $pago = $this->pago($pedido, 100);
        $pedido->update([
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->usuario->id,
        ]);

        $enviado = app(EnviarPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);

        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR, $enviado->estatus->fase_ciclo);
        $this->assertSame($remision->id, $enviado->remision()->first()->id);
        $this->assertNotNull($enviado->pago_validado_at);
        $this->assertSame($pago->id, PedidoBmaPago::query()->where('pedido_bma_id', $pedido->id)->first()->id);
        $this->assertSame(1, PedidoBma::query()->count());
    }

    public function test_fallo_durante_sustitucion_revierte_el_cierre(): void
    {
        $pedido = $this->pedido();
        $tarea = $this->tarea($pedido, CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO);
        $this->mock(AsegurarCumplimientoFisicoService::class)
            ->shouldReceive('ejecutar')
            ->once()
            ->andThrow(new \RuntimeException('fallo-forzado'));

        try {
            app(CambiarModalidadPreparacionService::class)->ejecutar(
                $tarea,
                $this->usuario,
                CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA,
                'Intento que falla'
            );
            $this->fail('La sustitución debía fallar.');
        } catch (\RuntimeException $e) {
            $this->assertSame('fallo-forzado', $e->getMessage());
        }

        $this->assertSame(PedidoBmaTareaPreparacion::ESTADO_PENDIENTE, $tarea->fresh()->estado);
        $this->assertSame(1, PedidoBmaTareaPreparacion::query()->count());
        $this->assertSame($tarea->id, $pedido->fresh()->instruccionEntregaVigente->id);
    }

    public function test_segunda_sustitucion_simultanea_no_deja_dos_vigentes(): void
    {
        $pedido = $this->pedido();
        $tarea = $this->tarea($pedido, CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO);
        $this->cumplimiento($tarea, PedidoBmaCumplimientoFisico::ESTADO_SEPARADA, 'Anaquel A-1');
        $primera = $this->sustituir($tarea, CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA, 'Primera');

        try {
            app(CambiarModalidadPreparacionService::class)->ejecutar(
                $tarea->fresh(),
                $this->usuario,
                CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_BODEGA_NORMAL,
                'Segunda en paralelo',
                (int) $tarea->version
            );
            $this->fail('La segunda sustitución debía rechazarse.');
        } catch (ValidationException $e) {
            $this->assertSame(InstruccionEntrega::MENSAJE_OBSOLETA, $e->errors()['instruccion'][0]);
        }

        $this->assertSame(1, InstruccionEntrega::abiertas((int) $pedido->id)->count());
        $this->assertSame($primera->id, $pedido->fresh()->instruccionEntregaVigente->id);
    }

    public function test_reintento_no_duplica_la_tarea(): void
    {
        $pedido = $this->pedido();
        $tarea = $this->tarea($pedido, CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO);
        $this->cumplimiento($tarea, PedidoBmaCumplimientoFisico::ESTADO_SEPARADA, 'Anaquel A-1');

        $primera = $this->sustituir($tarea, CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA, 'Mismo motivo');
        $reintento = $this->sustituir($tarea->fresh(), CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA, 'Mismo motivo');

        $this->assertSame($primera->id, $reintento->id);
        $this->assertSame(1, InstruccionEntrega::abiertas((int) $pedido->id)->count());
        $this->assertSame(2, PedidoBmaTareaPreparacion::query()->count());
    }

    public function test_reintento_de_solicitud_no_abre_otra_instruccion(): void
    {
        $pedido = $this->pedido([
            'folio_remision' => 'FOL-TAREA',
            'sucursal_destino_id' => null,
        ]);
        $this->documento($pedido, PedidoBmaDocumento::TIPO_PDF_PEDIDO);
        $this->habilitarPreparacion(
            ['ENVIO_MUNICIPIO', 'RECOGE_TIENDA', 'ENVIO_BODEGA_NORMAL'],
            [$pedido->almacen_id]
        );
        $servicio = app(CrearTareaPreparacionService::class);
        $base = [
            'revision_esperada' => (int) $pedido->fresh()->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($pedido->fresh()),
        ];

        $creada = $servicio->ejecutar(
            $pedido->fresh(),
            $this->usuario,
            CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_BODEGA_NORMAL,
            (int) $pedido->almacen_id,
            null,
            'clave-instruccion-1',
            [],
            [],
            $base
        );
        $reintento = $servicio->ejecutar(
            $pedido->fresh(),
            $this->usuario,
            CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_BODEGA_NORMAL,
            (int) $pedido->almacen_id,
            null,
            'clave-instruccion-1',
            [],
            [],
            $base
        );

        try {
            $servicio->ejecutar(
                $pedido->fresh(),
                $this->usuario,
                CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_BODEGA_NORMAL,
                (int) $pedido->almacen_id,
                null,
                'clave-instruccion-2'
            );
            $this->fail('Una segunda instrucción no debe abrirse.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('instrucción de entrega vigente', $e->errors()['tarea'][0]);
        }

        $this->assertSame($creada->id, $reintento->id);
        $this->assertSame(1, PedidoBmaTareaPreparacion::query()->count());
    }

    public function test_dos_instrucciones_abiertas_bloquean_la_accion_sin_adivinar(): void
    {
        $pedido = $this->pedido();
        $primera = $this->tarea($pedido, CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO, PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA);
        $segunda = $this->tarea($pedido, CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA);

        try {
            InstruccionEntrega::assertEjecutable($primera);
            $this->fail('La inconsistencia debía bloquear la acción.');
        } catch (ValidationException $e) {
            $this->assertSame(InstruccionEntrega::MENSAJE_AMBIGUA, $e->errors()['instruccion'][0]);
        }

        $this->assertSame(2, InstruccionEntrega::abiertas((int) $pedido->id)->count());
        $this->assertSame(PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA, $primera->fresh()->estado);
        $this->assertSame(PedidoBmaTareaPreparacion::ESTADO_PENDIENTE, $segunda->fresh()->estado);
    }

    public function test_municipal_despachada_no_pasa_a_recoleccion(): void
    {
        $pedido = $this->pedido();
        $tarea = $this->tarea($pedido, CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO);
        $this->cumplimiento($tarea, PedidoBmaCumplimientoFisico::ESTADO_DESPACHADA, 'Con el transporte');

        try {
            $this->sustituir($tarea, CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA, 'Quieren recoger');
            $this->fail('No debe convertirse a recolección.');
        } catch (ValidationException $e) {
            $this->assertSame(InstruccionEntrega::MENSAJE_RECOLECCION_TERMINAL, $e->errors()['modalidad'][0]);
        }

        $this->assertSame(PedidoBmaTareaPreparacion::ESTADO_PENDIENTE, $tarea->fresh()->estado);
        $this->assertSame(PedidoBmaCumplimientoFisico::ESTADO_DESPACHADA, $tarea->fresh()->cumplimientoFisico->estado);
    }

    public function test_cambio_de_paqueteria_despues_de_entrega_al_transporte_no_queda_registrado(): void
    {
        $pedido = $this->pedido([
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_ENVIADO)->id,
            'catalogo_paqueteria_id' => null,
            'numero_rastreo' => 'GUIA-1',
        ]);

        try {
            app(AsignarGuiaPedidoBmaService::class)->ejecutar($pedido, 'GUIA-NUEVA', $this->usuario->id);
            $this->fail('No debe asignar otra guía.');
        } catch (\RuntimeException $e) {
            $this->assertSame(InstruccionEntrega::MENSAJE_PAQUETERIA, $e->getMessage());
        }

        $this->assertSame('GUIA-1', $pedido->fresh()->numero_rastreo);
        $this->assertSame(0, $pedido->historial()->count());
    }

    public function test_segundo_traslado_exige_autorizacion_y_no_simula_recepcion(): void
    {
        $pedido = $this->pedido([
            'estatus_sucursal' => EstatusSucursalPedidoBma::EN_CUSTODIA,
        ]);
        $this->tarea($pedido, CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA);
        $origen = Sucursal::factory()->create(['activo' => true]);
        $pedido->update(['sucursal_destino_id' => $origen->id]);
        ResguardoPdv::factory()->create(['pedido_bma_id' => $pedido->id, 'sucursal_id' => $origen->id, 'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA]);
        $destino = Sucursal::factory()->create(['activo' => true]);
        $servicio = app(AsignarSucursalDestinoPedidoService::class);

        $solicitado = $servicio->ejecutar($pedido, $this->usuario, $destino->id, datosTraslado: ['motivo' => 'Corrección solicitada', 'error_captura' => true]);

        $this->assertSame(EstatusSucursalPedidoBma::EN_CUSTODIA, $solicitado->estatus_sucursal);
        $this->assertNotSame($destino->id, $solicitado->sucursal_destino_id);
        $this->assertSame('pendiente', $solicitado->solicitud_preparacion_json['segundo_traslado']['estado']);

        $salida = User::factory()->create();
        $salida->givePermissionTo(Permission::findOrCreate('control_pedidos.salida.autorizar', 'web'));
        try {
            app(ResolverSegundoTrasladoTiendaService::class)->ejecutar($solicitado, $salida, 'autorizar', 'Recorrido de prueba');
            $this->fail('El permiso de salida no autoriza el segundo traslado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('permiso', $e->errors());
        }

        $gerencia = User::factory()->create();
        $gerencia->assignRole(Role::findOrCreate('Administrador', 'web'));
        $gerencia->givePermissionTo(Permission::findOrCreate(SolicitarSegundoTrasladoTiendaService::PERMISO, 'web'));
        $actualizado = app(ResolverSegundoTrasladoTiendaService::class)->ejecutar(
            $solicitado->fresh(),
            $gerencia,
            'autorizar',
            'De la sucursal actual a la nueva, sin paso inventado por CEDIS.'
        );

        $this->assertSame($destino->id, $actualizado->sucursal_destino_id);
        $this->assertSame(EstatusSucursalPedidoBma::EN_CUSTODIA, $actualizado->estatus_sucursal);
        $this->assertFalse($actualizado->solicitud_preparacion_json['segundo_traslado']['recepcion_confirmada']);
    }

    public function test_sin_permiso_no_sustituye_la_instruccion(): void
    {
        $ajeno = User::factory()->create();
        $pedido = $this->pedido(['vendedor_id' => $ajeno->id]);
        $tarea = $this->tarea($pedido, CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO);

        try {
            app(CambiarModalidadPreparacionService::class)->ejecutar(
                $tarea,
                $ajeno,
                CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA,
                'Sin permiso'
            );
            $this->fail('Debía rechazar por permiso.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('permiso', $e->errors());
        }

        $this->assertSame(PedidoBmaTareaPreparacion::ESTADO_PENDIENTE, $tarea->fresh()->estado);
    }

    public function test_no_cambia_mercancia_cuando_hay_remision(): void
    {
        $pedido = $this->pedido([
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA)->id,
            'total_mercancia' => 100,
        ]);
        $this->remision($pedido);
        $fresco = $pedido->fresh();

        try {
            app(ActualizarPedidoBmaService::class)->ejecutar($fresco, [
                'revision_esperada' => (int) $fresco->revision_registro,
                'base_captura' => RevisionCapturaPedido::base($fresco),
                'total_mercancia' => 250,
            ], $this->usuario->id);
            $this->fail('No debe cambiar el importe.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('remisión', $e->getMessage());
        }

        $this->assertEquals(100, (float) $pedido->fresh()->total_mercancia);
    }

    /**
     * @param  list<string>  $modalidades
     * @param  list<int>  $almacenes
     */
    private function habilitarPreparacion(array $modalidades, array $almacenes = []): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PreparacionTiendaConfig::CLAVE_FLAG],
            ['valor' => json_encode([
                'activo' => true,
                'modalidades_habilitadas' => $modalidades,
                'departamentos_habilitados' => [],
                'almacenes_habilitados' => $almacenes,
                'usuarios_piloto' => [],
            ])]
        );
        app(PreparacionTiendaConfig::class)->olvidarCache();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pedido(array $extra = []): PedidoBma
    {
        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'Q1-'.uniqid(),
            'nombre' => 'Origen',
            'requiere_logistica' => true,
            'activo' => true,
        ]);
        $almacen = Almacen::query()->create([
            'codigo' => 'ALM-'.uniqid(),
            'nombre' => 'Salida',
            'activo' => true,
            'visible_en_pedidos' => true,
            'permite_salida_pedidos' => true,
        ]);
        $sucursal = Sucursal::factory()->create(['activo' => true]);

        return PedidoBma::query()->create(array_merge([
            'folio' => 'F1-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'origen_id' => $origen->id,
            'almacen_id' => $almacen->id,
            'sucursal_destino_id' => $sucursal->id,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR)->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'saldo_a_favor' => 0,
            'costo_seguro' => 0,
            'total_a_cobrar' => 100,
            'aplica_seguro' => false,
            'es_resguardo' => false,
        ], $extra));
    }

    private function tarea(PedidoBma $pedido, string $codigo, string $estado = PedidoBmaTareaPreparacion::ESTADO_PENDIENTE): PedidoBmaTareaPreparacion
    {
        $modalidad = CatalogoModalidadPreparacionPedido::query()->where('codigo', $codigo)->firstOrFail();

        return PedidoBmaTareaPreparacion::query()->create([
            'pedido_bma_id' => $pedido->id,
            'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => $pedido->almacen_id,
            'estado' => $estado,
            'solicitada_por_id' => $this->usuario->id,
            'version' => 1,
        ]);
    }

    private function cumplimiento(PedidoBmaTareaPreparacion $tarea, string $estado, string $ubicacion): PedidoBmaCumplimientoFisico
    {
        return PedidoBmaCumplimientoFisico::query()->create([
            'pedido_bma_tarea_preparacion_id' => $tarea->id,
            'estado' => $estado,
            'cantidad' => 2,
            'ubicacion' => $ubicacion,
            'version' => 1,
        ]);
    }

    private function sustituir(PedidoBmaTareaPreparacion $tarea, string $codigo, string $motivo): PedidoBmaTareaPreparacion
    {
        return app(CambiarModalidadPreparacionService::class)->ejecutar(
            $tarea,
            $this->usuario,
            $codigo,
            $motivo,
            (int) $tarea->version
        );
    }

    private function documento(PedidoBma $pedido, string $tipo): PedidoBmaDocumento
    {
        return PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => $tipo,
            'ruta_archivo' => 'pedidos_bma/'.$tipo.'.pdf',
            'nombre_original' => $tipo.'.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 20,
            'orden' => 1,
            'activo' => true,
        ]);
    }

    private function remision(PedidoBma $pedido): PedidoBmaDocumento
    {
        return $this->documento($pedido, PedidoBmaDocumento::TIPO_REMISION);
    }

    private function pago(PedidoBma $pedido, float $monto = 100): PedidoBmaPago
    {
        return PedidoBmaPago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => $monto,
            'ruta_archivo' => 'pedidos_bma/pago.jpg',
            'nombre_original' => 'pago.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 20,
            'estado_revision' => PedidoBmaPago::REVISION_VERIFICADO,
            'capturado_por_id' => $this->usuario->id,
            'activo_para_cobertura' => true,
        ]);
    }
}
