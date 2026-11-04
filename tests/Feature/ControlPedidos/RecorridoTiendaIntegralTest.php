<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\Cliente;
use App\Models\ConfiguracionSistema;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\Departamento;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\CancelarCreditoSinRemisionTiendaService;
use App\Services\ControlPedidos\CancelarPedidoBmaService;
use App\Services\ControlPedidos\ConfirmarEntregaCustodiaTiendaService;
use App\Services\ControlPedidos\ConfirmarEntregaRecogeHoyService;
use App\Services\ControlPedidos\ConfirmarPagoSalidaPreparacionService;
use App\Services\ControlPedidos\EjecutarSegundoTrasladoTiendaService;
use App\Services\ControlPedidos\EvaluarVencimientoApartadoFisicoService;
use App\Services\ControlPedidos\IntegrarAnexoFisicoTiendaService;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use App\Services\ControlPedidos\RegistrarAnexoTiendaService;
use App\Services\ControlPedidos\RegistrarRecepcionCustodiaTiendaService;
use App\Services\ControlPedidos\RegistrarRemisionCreditoTiendaService;
use App\Services\ControlPedidos\TrasladarCustodiaHaciaCedisService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\ControlPedidos\RegistrarCoberturaTiendaService;
use App\Services\ControlPedidos\RegistrarResultadoCreditoTiendaService;
use App\Services\ControlPedidos\RetirarCustodiaParaEnvioService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
use App\Support\ControlPedidos\PoliticaSalidaPreparacion;
use App\Support\ControlPedidos\RegistrarPedidos\PoliticaAccionesRegistrarPedidos;
use App\Support\SaldosAFavor\AlcanceSaf;
use App\Support\SaldosAFavor\ReglasSaf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RecorridoTiendaIntegralTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Departamento $departamento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->departamento = Departamento::query()->create(['nombre' => 'Ventas', 'activo' => true]);
        $this->usuario = User::factory()->create(['departamento_id' => $this->departamento->id]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        foreach ([
            CatalogoEstatusPedido::FASE_BORRADOR => 'BORRADOR',
        ] as $fase => $codigo) {
            CatalogoEstatusPedido::query()->firstOrCreate(
                ['fase_ciclo' => $fase],
                ['codigo_interno' => $codigo, 'nombre_visual' => $fase, 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
            );
        }
        foreach ([
            CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA => 'Recoge en tienda',
            CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_TRANSFERENCIA => 'Transferencia',
            CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO => 'Crédito',
        ] as $codigo => $nombre) {
            CatalogoModalidadPreparacionPedido::query()->firstOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'activo' => true]
            );
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_la_cobertura_de_mismo_dia_exige_facultad_y_dia_habil_sin_confirmarla(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-07 10:00:00', 'America/Mexico_City'));
        $pedido = $this->pedido([
            'consulta_cerrada_at' => now(),
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'revision_disponibilidad' => ['estado' => 'sin_excepciones', 'confirmada' => false],
            ],
        ]);
        $this->usuario->givePermissionTo(Permission::findOrCreate(PoliticaAccionesRegistrarPedidos::PERMISO_MD, 'web'));
        try {
            app(RegistrarCoberturaTiendaService::class)->ejecutar($pedido, $this->usuario, 'transferencia', true);
            $this->fail('El fin de semana no deja prevista la atención de mismo día.');
        } catch (ValidationException $e) {
            $this->assertSame(PoliticaAccionesRegistrarPedidos::MENSAJE_SABADO, $e->errors()['atencion_tienda'][0]);
        }

        Carbon::setTestNow(Carbon::parse('2026-11-03 10:00:00', 'America/Mexico_City'));
        $sinFacultad = User::factory()->create(['departamento_id' => $this->departamento->id]);
        $pedidoFacultad = $this->pedido([
            'vendedor_id' => $sinFacultad->id,
            'consulta_cerrada_at' => now(),
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'revision_disponibilidad' => ['estado' => 'sin_excepciones', 'confirmada' => false],
            ],
        ]);
        try {
            app(RegistrarCoberturaTiendaService::class)->ejecutar($pedidoFacultad, $sinFacultad, 'transferencia', true);
            $this->fail('Sin facultad no queda prevista la atención.');
        } catch (ValidationException $e) {
            $this->assertSame(PoliticaAccionesRegistrarPedidos::MENSAJE_FACULTAD, $e->errors()['atencion_tienda'][0]);
        }

        $registrada = app(RegistrarCoberturaTiendaService::class)->ejecutar($pedido->fresh(), $this->usuario, 'transferencia', true);
        $this->assertSame('prevista', $registrada->solicitud_preparacion_json['atencion_tienda']);
        $this->assertFalse($registrada->solicitud_preparacion_json['cobertura_tienda']['confirmada']);
        $this->assertFalse($registrada->prioridad_md);
    }

    public function test_el_apartado_se_cobra_y_entrega_con_fotografia(): void
    {
        Storage::fake('local');
        $pedido = $this->pedido();
        $tarea = $this->tarea($pedido, CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA);
        $cumplimiento = PedidoBmaCumplimientoFisico::query()->create([
            'pedido_bma_tarea_preparacion_id' => $tarea->id,
            'estado' => PedidoBmaCumplimientoFisico::ESTADO_APARTADO_CONFIRMADO,
            'cantidad' => 1,
            'version' => 1,
        ]);
        $caja = User::factory()->create();
        $caja->givePermissionTo(Permission::findOrCreate('control_pedidos.salida.validar_pago', 'web'));
        $caja->givePermissionTo(Permission::findOrCreate('control_pedidos.salida.confirmar_entrega', 'web'));

        $pagado = app(ConfirmarPagoSalidaPreparacionService::class)->ejecutar(
            $tarea,
            $caja,
            PoliticaSalidaPreparacion::COBRO_PAGADO,
            null,
            'FOL-PRESENCIAL'
        );
        $this->assertNotNull($pagado->pago_confirmado_at);
        $this->assertSame(PedidoBmaCumplimientoFisico::ESTADO_APARTADO_CONFIRMADO, $pagado->estado);

        $entregado = app(ConfirmarEntregaRecogeHoyService::class)->ejecutar(
            $tarea->fresh(),
            $caja,
            'Quien recibe en sucursal',
            (int) $pagado->version,
            UploadedFile::fake()->image('entrega.jpg')
        );
        $this->assertSame(PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA, $entregado->estado);
        $this->assertSame(1, PedidoBmaTareaDocumento::query()
            ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
            ->where('tipo_evidencia', PedidoBmaTareaDocumento::TIPO_EVIDENCIA_ENTREGA)
            ->count());
        $this->assertSame($cumplimiento->id, $entregado->id);
    }

    public function test_la_misma_referencia_no_crea_un_segundo_ingreso(): void
    {
        Storage::fake('public');
        $pedido = $this->pedido();
        $pagos = app(RegistrarPagoPedidoBmaService::class);
        $comprobante = fn (string $nombre) => UploadedFile::fake()->create($nombre, 20, 'application/pdf');
        $primero = $pagos->handle($pedido, [
            'monto' => 40,
            'forma_pago' => 'efectivo',
            'referencia' => 'SPEI-77',
            'idempotency_key' => 'ingreso-1',
        ], $comprobante('spei-77.pdf'), $this->usuario->id);
        $this->assertArrayNotHasKey('tienda_md', $pedido->fresh()->solicitud_preparacion_json);
        $this->assertArrayNotHasKey('tienda_md_reporte', $pedido->fresh()->solicitud_preparacion_json);

        try {
            $pagos->handle($pedido->fresh(), [
                'monto' => 40,
                'forma_pago' => 'efectivo',
                'referencia' => 'SPEI-77',
                'idempotency_key' => 'ingreso-2',
            ], $comprobante('spei-77-b.pdf'), $this->usuario->id);
            $this->fail('La misma referencia no es un segundo ingreso.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('ya está aplicado', $e->getMessage());
        }

        $segundo = $pagos->handle($pedido->fresh(), [
            'monto' => 15,
            'forma_pago' => 'efectivo',
            'referencia' => 'SPEI-78',
            'idempotency_key' => 'ingreso-3',
        ], $comprobante('spei-78.pdf'), $this->usuario->id);
        $this->assertNotSame($primero->id, $segundo->id);
        $this->assertSame(2, PedidoBmaPago::query()->where('pedido_bma_id', $pedido->id)->count());
        $this->assertArrayNotHasKey('tienda_md', $pedido->fresh()->solicitud_preparacion_json);
        $this->assertSame((float) $primero->monto, (float) $primero->fresh()->monto);
    }

    public function test_el_credito_aprobado_no_pide_transferencia_y_el_bloqueado_sigue_bloqueado(): void
    {
        $auxiliar = User::factory()->create();
        $auxiliar->givePermissionTo(Permission::findOrCreate('control_pedidos.auditar', 'web'));
        $pedido = $this->pedido([
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'codigo_modalidad' => CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO,
            ],
        ]);

        $bloqueado = app(RegistrarResultadoCreditoTiendaService::class)->ejecutar($pedido, $auxiliar, 'bloqueado', 'El límite no alcanza.');
        $this->assertSame('bloqueado', $bloqueado->solicitud_preparacion_json['credito_wizerp']['resultado']);
        $this->assertSame(0, $bloqueado->pagosExhibicion()->count());
        $this->assertNull($bloqueado->pago_validado_at);

        $aprobado = $this->pedido([
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'codigo_modalidad' => CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO,
            ],
        ]);
        $resultado = app(RegistrarResultadoCreditoTiendaService::class)->ejecutar($aprobado, $auxiliar, 'aprobado');
        $this->assertSame('aprobado', $resultado->solicitud_preparacion_json['credito_wizerp']['resultado']);
        $this->assertSame(1, $resultado->pagosExhibicion()->where('forma_pago', 'credito')->count());
        $this->assertNull($resultado->pago_validado_at);
        $this->assertSame(0, SafCredito::query()->count());
    }

    public function test_el_anexo_no_edita_la_remision_y_el_retiro_no_confirma_cedis(): void
    {
        Storage::fake('public');
        $pedido = $this->pedido();
        $anexo = app(RegistrarAnexoTiendaService::class)->ejecutar(
            $pedido,
            $this->usuario,
            'SKU-1',
            'Pieza adicional',
            1,
            'Regular, consultada en sucursal',
            UploadedFile::fake()->create('anexo.pdf', 20, 'application/pdf')
        );
        $this->assertSame($pedido->id, $anexo->id);
        $this->assertFalse($anexo->solicitud_preparacion_json['anexo_integrado']);
        $this->assertSame(1, $anexo->documentos()->where('tipo', PedidoBmaDocumento::TIPO_ANEXO_PIEZAS)->count());

        $conRemision = $this->pedido(['numero_remision' => 'REM-900', 'pago_validado_at' => now()]);
        $hijo = app(RegistrarAnexoTiendaService::class)->ejecutar(
            $conRemision,
            $this->usuario,
            'SKU-2',
            'Pieza posterior',
            1,
            'Disponible',
            UploadedFile::fake()->create('anexo-2.pdf', 20, 'application/pdf')
        );
        $this->assertSame($conRemision->id, $hijo->pedido_principal_id);
        $this->assertNull($hijo->solicitud_preparacion_json['cobertura_tienda']);
        $this->assertSame('REM-900', $conRemision->fresh()->numero_remision);
        $this->assertSame(0, $hijo->pagosExhibicion()->count());

        $conRemision->update(['estatus_sucursal' => EstatusSucursalPedidoBma::EN_CUSTODIA]);
        $sucursal = Sucursal::factory()->create(['activo' => true]);
        ResguardoPdv::query()->create([
            'pedido_bma_id' => $conRemision->id,
            'cliente_id' => $conRemision->cliente_id,
            'sucursal_id' => $sucursal->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'cantidad_bultos_esperada' => 1,
            'entrega_bloqueada' => false,
            'snapshot_folio' => $conRemision->folio,
            'snapshot_json' => ['folio' => $conRemision->folio],
            'version' => 1,
        ]);
        $principal = $this->pedido(['cliente_id' => $conRemision->cliente_id]);
        $retirado = app(RetirarCustodiaParaEnvioService::class)->ejecutar($conRemision->fresh(), $principal, $this->usuario);
        $this->assertSame('pendiente_traslado', $retirado->solicitud_preparacion_json['retiro_recoleccion']['estado']);
        $this->assertFalse($retirado->solicitud_preparacion_json['retiro_recoleccion']['integrado_cedis']);
        $this->assertSame(EstatusSucursalPedidoBma::EN_CUSTODIA, $retirado->estatus_sucursal);
        $this->assertTrue((bool) ResguardoPdv::query()->where('pedido_bma_id', $conRemision->id)->value('entrega_bloqueada'));
    }

    public function test_los_veinte_dias_reintegran_fisicamente_sin_crear_saldo(): void
    {
        $pedido = $this->pedido(['numero_remision' => 'REM-20']);
        $tarea = $this->tarea($pedido, CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_TRANSFERENCIA);
        PedidoBmaCumplimientoFisico::query()->create([
            'pedido_bma_tarea_preparacion_id' => $tarea->id,
            'estado' => PedidoBmaCumplimientoFisico::ESTADO_EMPACADA,
            'cantidad' => 1,
            'version' => 1,
        ]);
        $documento = PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_REMISION,
            'ruta_archivo' => 'pedidos_bma/remision.pdf',
            'nombre_original' => 'remision.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 100,
            'orden' => 1,
            'activo' => true,
        ]);
        $documento->forceFill(['created_at' => now()->subDays(21), 'updated_at' => now()->subDays(21)])->save();
        $tarea->update(['estado' => PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION]);
        $cumplimiento = $tarea->cumplimientoFisico;
        $cumplimiento->update(['estado' => PedidoBmaCumplimientoFisico::ESTADO_SEPARADA, 'cantidad' => 1]);
        $producto = $this->producto('SKU-20');
        $inventario = Inventario::query()->create([
            'producto_id' => $producto->id,
            'almacen_id' => $pedido->almacen_id,
            'existencia' => '4.000',
            'apartado' => '0.000',
        ]);
        $linea = $tarea->productos()->create([
            'producto_id' => $producto->id,
            'sku' => $producto->sku,
            'descripcion_snapshot' => 'Pieza en custodia',
            'cantidad_solicitada' => 1,
            'cantidad_encontrada' => 1,
            'orden' => 1,
        ]);
        app(\App\Services\ControlPedidos\CompromisoInventarioTienda::class)->comprometer(
            $pedido,
            (int) $pedido->almacen_id,
            $producto->id,
            '1',
            'tarea:'.$tarea->id.':producto:'.$linea->id,
            $producto->sku
        );
        $this->assertSame('1.000', $inventario->fresh()->apartado);

        $movidas = app(EvaluarVencimientoApartadoFisicoService::class)->ejecutar();
        $fresco = $pedido->fresh();
        $this->assertGreaterThan(0, $movidas);
        $this->assertSame('solicitada', $fresco->solicitud_preparacion_json['liberacion_fisica_20']['estado']);
        $this->assertSame('apartado', $fresco->solicitud_preparacion_json['liberacion_fisica_20']['reintegro']);
        $this->assertSame('pendiente_de_definicion', $fresco->solicitud_preparacion_json['liberacion_fisica_20']['dinero']);
        $this->assertSame('0.000', $inventario->fresh()->apartado);
        $this->assertSame('4.000', $inventario->fresh()->existencia);
        $this->assertSame(0, SafCredito::query()->count());
        $this->assertSame(PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE, $tarea->fresh()->cumplimientoFisico->estado);
    }

    public function test_la_transferencia_se_recibe_en_custodia_y_se_entrega(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $sucursal = Sucursal::factory()->create(['activo' => true]);
        $pedido = $this->pedido([
            'numero_remision' => 'REM-T02',
            'pago_validado_at' => now(),
            'sucursal_destino_id' => $sucursal->id,
        ]);
        $tarea = $this->tarea($pedido, CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_TRANSFERENCIA);
        $producto = $this->producto('SKU-T02');
        $inventario = Inventario::query()->create([
            'producto_id' => $producto->id,
            'almacen_id' => $pedido->almacen_id,
            'existencia' => '5.000',
            'apartado' => '0.000',
        ]);
        $tarea->productos()->create([
            'producto_id' => $producto->id,
            'sku' => $producto->sku,
            'descripcion_snapshot' => 'Pieza de transferencia',
            'cantidad_solicitada' => 1,
            'cantidad_encontrada' => 1,
            'orden' => 1,
        ]);
        PedidoBmaCumplimientoFisico::query()->create([
            'pedido_bma_tarea_preparacion_id' => $tarea->id,
            'estado' => PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR,
            'cantidad' => 1,
            'version' => 1,
        ]);
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.tienda.responder', 'web'));
        $this->usuario->givePermissionTo(Permission::findOrCreate(PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR, 'web'));
        $this->usuario->concederAccesoSucursal($sucursal, true);

        $recibida = app(RegistrarRecepcionCustodiaTiendaService::class)->ejecutar($tarea, $this->usuario);
        $this->assertSame(EstatusSucursalPedidoBma::EN_CUSTODIA, $recibida->pedido->estatus_sucursal);
        $this->assertSame(PedidoBmaCumplimientoFisico::ESTADO_LISTA_PARA_SALIDA, $recibida->cumplimientoFisico->estado);
        $this->assertNotNull($recibida->cumplimientoFisico->resguardo_pdv_id);
        $this->assertSame('1.000', $inventario->fresh()->apartado);
        $this->assertSame('5.000', $inventario->fresh()->existencia);

        $entregada = app(ConfirmarEntregaCustodiaTiendaService::class)->ejecutar(
            $recibida,
            $this->usuario,
            'Quien recibe en sucursal',
            UploadedFile::fake()->image('custodia.jpg')
        );
        $this->assertSame(PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA, $entregada->cumplimientoFisico->estado);
        $this->assertSame('entregada', $entregada->pedido->solicitud_preparacion_json['entrega_custodia']['estado']);
        $this->assertSame('0.000', $inventario->fresh()->apartado);
        $this->assertSame('4.000', $inventario->fresh()->existencia);
        $this->assertSame(0, SafCredito::query()->count());
    }

    public function test_el_segundo_traslado_se_mueve_despues_de_autorizarlo(): void
    {
        $origen = Sucursal::factory()->create(['activo' => true, 'nombre' => 'Origen']);
        $destino = Sucursal::factory()->create(['activo' => true, 'nombre' => 'Destino']);
        $pedido = $this->pedido([
            'sucursal_destino_id' => $origen->id,
            'estatus_sucursal' => EstatusSucursalPedidoBma::EN_CUSTODIA,
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'segundo_traslado' => [
                    'estado' => 'autorizada',
                    'sucursal_destino_id' => $destino->id,
                    'recepcion_confirmada' => false,
                    'recorrido' => 'Origen a destino, sin paso por CEDIS',
                ],
            ],
        ]);
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.tienda.trasladar', 'web'));
        $servicio = app(EjecutarSegundoTrasladoTiendaService::class);

        try {
            $servicio->recibir($pedido, $this->usuario);
            $this->fail('La recepción no ocurre antes de la salida.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('salida', $e->errors()['traslado'][0]);
        }

        $enCamino = $servicio->salir($pedido, $this->usuario);
        $this->assertSame('en_camino', $enCamino->solicitud_preparacion_json['segundo_traslado']['movimiento_estado']);
        $this->assertFalse($enCamino->solicitud_preparacion_json['segundo_traslado']['recepcion_confirmada']);
        $this->assertSame(EstatusSucursalPedidoBma::EN_TRANSITO, $enCamino->estatus_sucursal);

        $recibido = $servicio->recibir($enCamino, $this->usuario);
        $this->assertTrue($recibido->solicitud_preparacion_json['segundo_traslado']['recepcion_confirmada']);
        $this->assertSame('recibido', $recibido->solicitud_preparacion_json['segundo_traslado']['movimiento_estado']);
        $this->assertSame(EstatusSucursalPedidoBma::EN_CUSTODIA, $recibido->estatus_sucursal);
        $this->assertSame($destino->id, $recibido->sucursal_destino_id);
    }

    public function test_el_retiro_de_pdv_se_traslada_y_cedis_lo_integra(): void
    {
        CatalogoEstadoSolicitud::query()->firstOrCreate(['nombre' => 'Pendiente'], ['activo' => true]);
        $sucursal = Sucursal::factory()->create(['activo' => true]);
        $custodia = $this->pedido([
            'numero_remision' => 'REM-PDV',
            'pago_validado_at' => now(),
            'estatus_sucursal' => EstatusSucursalPedidoBma::EN_CUSTODIA,
            'sucursal_destino_id' => $sucursal->id,
        ]);
        $tarea = $this->tarea($custodia, CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_TRANSFERENCIA);
        $producto = $this->producto('SKU-PDV');
        $tarea->productos()->create([
            'producto_id' => $producto->id,
            'sku' => $producto->sku,
            'descripcion_snapshot' => 'Bolsa de recolección',
            'cantidad_solicitada' => 1,
            'cantidad_encontrada' => 1,
            'orden' => 1,
        ]);
        $principal = $this->pedido(['cliente_id' => $custodia->cliente_id]);
        ResguardoPdv::query()->create([
            'pedido_bma_id' => $custodia->id,
            'cliente_id' => $custodia->cliente_id,
            'sucursal_id' => $sucursal->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'cantidad_bultos_esperada' => 1,
            'entrega_bloqueada' => false,
            'snapshot_folio' => $custodia->folio,
            'snapshot_json' => ['folio' => $custodia->folio],
            'version' => 1,
        ]);
        $retirada = app(RetirarCustodiaParaEnvioService::class)->ejecutar($custodia, $principal, $this->usuario);
        $this->assertFalse($retirada->solicitud_preparacion_json['retiro_recoleccion']['integrado_cedis']);

        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.tienda.trasladar', 'web'));
        $enviada = app(TrasladarCustodiaHaciaCedisService::class)->enviar($retirada, $this->usuario);
        $this->assertSame('en_traslado', $enviada->solicitud_preparacion_json['retiro_recoleccion']['estado']);
        $this->assertFalse($enviada->solicitud_preparacion_json['retiro_recoleccion']['integrado_cedis']);
        $this->assertSame('en_traslado', $principal->fresh()->solicitud_preparacion_json['complemento_pdv']['estado']);
        $this->assertFalse($principal->fresh()->solicitud_preparacion_json['complemento_pdv']['integrado']);

        $cedis = User::factory()->create();
        $cedis->givePermissionTo(Permission::findOrCreate('control_pedidos.cedis', 'web'));
        $recibida = app(TrasladarCustodiaHaciaCedisService::class)->recibir($enviada, $cedis);
        $this->assertTrue($recibida->solicitud_preparacion_json['retiro_recoleccion']['integrado_cedis']);
        $this->assertSame(PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS, $tarea->fresh()->estado);
        $this->assertTrue($principal->fresh()->solicitud_preparacion_json['complemento_pdv']['integrado']);
        $this->assertSame('REM-PDV', $recibida->numero_remision);
    }

    public function test_el_anexo_se_cubre_se_remisiona_y_se_integra(): void
    {
        Storage::fake('public');
        $principal = $this->pedido(['numero_remision' => 'REM-PRI', 'pago_validado_at' => now()]);
        $anexo = app(RegistrarAnexoTiendaService::class)->ejecutar(
            $principal,
            $this->usuario,
            'SKU-ANX',
            'Pieza de anexo',
            1,
            'Disponible en sucursal',
            UploadedFile::fake()->create('anexo.pdf', 20, 'application/pdf')
        );
        $this->assertFalse($anexo->solicitud_preparacion_json['anexo_integrado']);
        $anexo->update([
            'pago_validado_at' => now(),
            'solicitud_preparacion_json' => array_merge($anexo->solicitud_preparacion_json, [
                'cobertura_tienda' => ['tipo' => 'transferencia'],
            ]),
        ]);
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.tienda.responder', 'web'));
        $remisionado = app(RegistrarRemisionCreditoTiendaService::class)->ejecutar(
            $anexo->fresh(),
            $this->usuario,
            'REM-ANX',
            UploadedFile::fake()->create('remision-anexo.pdf', 20, 'application/pdf')
        );
        $this->assertSame('REM-PRI', $principal->fresh()->numero_remision);
        $this->assertSame('REM-ANX', $remisionado->numero_remision);

        $integrado = app(IntegrarAnexoFisicoTiendaService::class)->ejecutar($remisionado, $this->usuario);
        $this->assertTrue($integrado->solicitud_preparacion_json['anexo_integrado']);
        $this->assertSame('integrado', $integrado->solicitud_preparacion_json['anexo_integracion']['estado']);
        $this->assertTrue($principal->fresh()->solicitud_preparacion_json['anexo_integrado']);
        $this->assertSame('REM-PRI', $principal->fresh()->numero_remision);
        $this->assertSame(PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA, $integrado->tareasPreparacion()->first()->estado);
    }

    public function test_el_credito_se_remisiona_entrega_y_se_cancela_al_terminar_el_dia_siguiente(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_CANCELADO],
            ['codigo_interno' => 'CANCELADO', 'nombre_visual' => 'Cancelado', 'color_hex' => '#999999', 'orden' => 9, 'activo' => true]
        );
        $sucursal = Sucursal::factory()->create(['activo' => true]);
        $auxiliar = User::factory()->create();
        $auxiliar->givePermissionTo(Permission::findOrCreate('control_pedidos.auditar', 'web'));
        $pedido = $this->pedido([
            'sucursal_destino_id' => $sucursal->id,
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'codigo_modalidad' => CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO,
            ],
        ]);
        $aprobado = app(RegistrarResultadoCreditoTiendaService::class)->ejecutar($pedido, $auxiliar, 'aprobado');
        $remisionado = app(RegistrarRemisionCreditoTiendaService::class)->ejecutar(
            $aprobado, $auxiliar, 'REM-CRED', UploadedFile::fake()->create('credito.pdf', 20, 'application/pdf')
        );
        $this->assertSame('REM-CRED', $remisionado->numero_remision);
        $tarea = $this->tarea($remisionado, CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO);
        $producto = $this->producto('SKU-CRED');
        Inventario::query()->create([
            'producto_id' => $producto->id, 'almacen_id' => $pedido->almacen_id, 'existencia' => '2.000', 'apartado' => '0.000',
        ]);
        $tarea->productos()->create([
            'producto_id' => $producto->id, 'sku' => $producto->sku, 'descripcion_snapshot' => 'Pieza a crédito',
            'cantidad_solicitada' => 1, 'cantidad_encontrada' => 1, 'orden' => 1,
        ]);
        PedidoBmaCumplimientoFisico::query()->create([
            'pedido_bma_tarea_preparacion_id' => $tarea->id,
            'estado' => PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR,
            'cantidad' => 1,
            'version' => 1,
        ]);
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.tienda.responder', 'web'));
        $this->usuario->givePermissionTo(Permission::findOrCreate(PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR, 'web'));
        $this->usuario->concederAccesoSucursal($sucursal, true);
        $recibida = app(RegistrarRecepcionCustodiaTiendaService::class)->ejecutar($tarea, $this->usuario);
        $entregada = app(ConfirmarEntregaCustodiaTiendaService::class)->ejecutar(
            $recibida, $this->usuario, 'Quien recibe', UploadedFile::fake()->image('credito.jpg')
        );
        $this->assertSame(PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA, $entregada->cumplimientoFisico->estado);
        $this->assertSame(0, SafCredito::query()->count());

        $reciente = $this->pedido([
            'fecha' => now()->subDay()->toDateString(),
            'solicitud_preparacion_json' => ['version' => 1, 'credito_wizerp' => ['resultado' => 'bloqueado']],
        ]);
        $vencido = $this->pedido([
            'fecha' => now()->subDays(2)->toDateString(),
            'solicitud_preparacion_json' => ['version' => 1, 'credito_wizerp' => ['resultado' => 'bloqueado']],
        ]);
        $cancelados = app(CancelarCreditoSinRemisionTiendaService::class)->ejecutar();
        $this->assertSame(1, $cancelados);
        $this->assertNull($reciente->fresh()->cancelado_at);
        $this->assertNotNull($vencido->fresh()->cancelado_at);
        $this->assertSame(CancelarPedidoBmaService::RESOLUCION_NINGUNA, $vencido->fresh()->resolucion_financiera_cancelacion);
        $this->assertSame(0, SafCredito::query()->count());
    }

    public function test_cambiar_modalidad_exige_archivo_y_conserva_pago_y_remision(): void
    {
        Storage::fake('public');
        $this->habilitarPreparacion([
            CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA,
            CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_BODEGA_NORMAL,
        ]);
        CatalogoModalidadPreparacionPedido::query()->firstOrCreate(
            ['codigo' => CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_BODEGA_NORMAL],
            ['nombre' => 'Envío bodega', 'activo' => true]
        );
        $pedido = $this->pedido(['numero_remision' => 'REM-MOD']);
        app(RegistrarPagoPedidoBmaService::class)->handle($pedido, [
            'monto' => 40,
            'forma_pago' => 'efectivo',
            'referencia' => 'PAGO-MOD',
            'idempotency_key' => 'pago-mod',
        ], UploadedFile::fake()->create('pago-mod.pdf', 20, 'application/pdf'), $this->usuario->id);
        $tarea = $this->tarea($pedido, CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA);
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.preparacion.solicitar', 'web'));
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('control_pedidos.preparacion.cambiar_modalidad', $tarea), [
                'codigo_modalidad' => CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_BODEGA_NORMAL,
                'motivo' => 'La clientela pide envío',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('evidencia');

        $this->actingAs($this->usuario)
            ->post(route('control_pedidos.preparacion.cambiar_modalidad', $tarea), [
                'codigo_modalidad' => CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_BODEGA_NORMAL,
                'motivo' => 'La clientela pide envío',
                'evidencia' => UploadedFile::fake()->create('cambio.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $pedido->refresh();
        $this->assertSame('REM-MOD', $pedido->numero_remision);
        $this->assertSame(1, $pedido->pagosExhibicion()->count());
        $this->assertSame(PedidoBmaTareaPreparacion::ESTADO_SUSTITUIDA, $tarea->fresh()->estado);
        $vigente = $pedido->tareasPreparacion()->where('estado', PedidoBmaTareaPreparacion::ESTADO_PENDIENTE)->first();
        $this->assertNotNull($vigente);
        $this->assertSame('envio', $pedido->solicitud_preparacion_json['cambio_modalidad']['circuito_receptor']);
        $this->assertSame($vigente->id, $pedido->solicitud_preparacion_json['cambio_modalidad']['tarea_nueva_id']);
    }

    public function test_el_excedente_menor_a_diez_no_genera_saldo_y_no_se_aplica_al_mismo_pedido(): void
    {
        try {
            ReglasSaf::assertMontoMinimo(9.99);
            $this->fail('Un excedente menor a 10 no genera saldo.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('10.00', $e->getMessage());
        }
        ReglasSaf::assertMontoMinimo(10);
        $pedido = $this->pedido();
        $this->assertSame($pedido->id, AlcanceSaf::desdePedido($pedido)['excluir_pedido_bma_id']);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pedido(array $extra = []): PedidoBma
    {
        $listaId = DB::table('catalogo_listas_descuento')->insertGetId([
            'nombre' => 'Lista recorrido '.uniqid(),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $clienteId = $extra['cliente_id'] ?? Cliente::query()->create([
            'numero_cliente' => (string) random_int(10000, 99999),
            'nombre' => 'Cliente ficticio',
            'lista_actual_id' => $listaId,
            'vendedor_id' => $this->usuario->id,
        ])->id;
        unset($extra['cliente_id']);
        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'ORI-'.uniqid(),
            'nombre' => 'Mostrador '.uniqid(),
            'requiere_logistica' => true,
            'activo' => true,
        ]);
        $salida = Almacen::query()->create([
            'codigo' => 'CED-'.uniqid(),
            'nombre' => 'Salida general',
            'activo' => true,
            'visible_en_pedidos' => true,
            'permite_salida_pedidos' => true,
        ]);

        return PedidoBma::query()->create(array_merge([
            'folio' => 'INT-'.uniqid(),
            'folio_remision' => 'FOL-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => $clienteId,
            'departamento_operacion_id' => $this->departamento->id,
            'origen_id' => $origen->id,
            'almacen_id' => $salida->id,
            'sucursal_destino_id' => Sucursal::factory()->create(['activo' => true])->id,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR)->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'saldo_a_favor' => 0,
            'costo_seguro' => 0,
            'total_a_cobrar' => 100,
            'aplica_seguro' => false,
            'es_resguardo' => false,
            'prioridad_md' => false,
            'solicitud_preparacion_json' => ['version' => 1, 'modalidad_entrega' => 'tienda'],
        ], $extra));
    }

    private function producto(string $sku): Producto
    {
        return Producto::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'sku' => $sku.'-'.uniqid(),
            'descripcion' => 'Pieza ficticia '.$sku,
            'folio' => (string) random_int(100000, 999999),
            'activo' => true,
        ]);
    }

    /**
     * @param  list<string>  $modalidades
     */
    private function habilitarPreparacion(array $modalidades): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PreparacionTiendaConfig::CLAVE_FLAG],
            ['valor' => json_encode([
                'activo' => true,
                'modalidades_habilitadas' => $modalidades,
                'departamentos_habilitados' => [],
                'almacenes_habilitados' => [],
                'usuarios_piloto' => [],
            ])]
        );
        app(PreparacionTiendaConfig::class)->olvidarCache();
    }

    private function tarea(PedidoBma $pedido, string $codigo): PedidoBmaTareaPreparacion
    {
        $modalidad = CatalogoModalidadPreparacionPedido::query()->where('codigo', $codigo)->firstOrFail();

        return PedidoBmaTareaPreparacion::query()->create([
            'pedido_bma_id' => $pedido->id,
            'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => $pedido->almacen_id,
            'estado' => PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            'solicitada_por_id' => $this->usuario->id,
            'version' => 1,
        ]);
    }
}
