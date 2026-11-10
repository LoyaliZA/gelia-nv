<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoTipoAlmacen;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\Departamento;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\Sucursal;
use App\Models\User;
use App\Notifications\AlertaPedidoBma;
use App\Services\ControlPedidos\DevolverCreditoVencidoTiendaService;
use App\Services\ControlPedidos\GestionarRemisionPedidoBmaService;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use App\Services\ControlPedidos\SeguimientoRecoleccionT02Service;
use App\Services\ControlPedidos\SolicitarSegundoTrasladoTiendaService;
use App\Services\ControlPedidos\TransferenciaTiendaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TransferenciaTiendaTest extends TestCase
{
    use RefreshDatabase;

    private User $cedis;

    private User $auxiliar;

    private User $vendedora;

    private Almacen $destino;

    private PedidoBma $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Storage::fake('public');
        Storage::fake('local');
        Notification::fake();
        $depto = Departamento::query()->create(['nombre' => 'Tienda T02', 'activo' => true]);
        $this->cedis = User::factory()->create(['departamento_id' => $depto->id]);
        $this->vendedora = User::factory()->create(['departamento_id' => $depto->id]);
        $this->vendedora->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $this->auxiliar = User::factory()->create(['departamento_id' => $depto->id]);
        foreach (['control_pedidos.cedis', 'control_pedidos.cedis.empacar', 'control_pedidos.cedis.enviar'] as $permiso) {
            $this->cedis->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        foreach (['control_pedidos.tienda.responder', 'pdv.resguardos.entregar', 'pdv.acceder'] as $permiso) {
            $this->auxiliar->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        $sucursal = Sucursal::factory()->create(['activo' => true]);
        $this->auxiliar->concederAccesoSucursal($sucursal, true);
        $this->destino = Almacen::query()->create(['codigo' => 'PDV', 'nombre' => 'Custodia destino', 'sucursal_id' => $sucursal->id, 'activo' => true]);
        $origen = Almacen::query()->create(['codigo' => 'CED-T02', 'nombre' => 'CEDIS origen', 'activo' => true]);
        $origenPedido = CatalogoOrigenPedido::query()->create(['codigo' => 'ORI-T02', 'nombre' => 'Tienda', 'requiere_logistica' => false, 'activo' => true]);
        foreach (['EN_CEDIS', 'PENDIENTE_DE_ENVIO', 'ENVIADO', 'ENTREGADO', 'PENDIENTE_AUXILIAR'] as $indice => $fase) {
            CatalogoEstatusPedido::query()->firstOrCreate(['fase_ciclo' => $fase], ['codigo_interno' => $fase, 'nombre_visual' => $fase, 'color_hex' => '#999999', 'orden' => $indice, 'activo' => true]);
        }
        CatalogoModalidadPreparacionPedido::query()->firstOrCreate(['codigo' => 'RECOGE_TIENDA_TRANSFERENCIA'], ['nombre' => 'Transferencia', 'activo' => true]);
        $lista = DB::table('catalogo_listas_descuento')->insertGetId(['nombre' => 'Lista T02', 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
        $cliente = Cliente::query()->create(['numero_cliente' => 'T02001', 'nombre' => 'Cliente T02', 'lista_actual_id' => $lista, 'vendedor_id' => $this->vendedora->id]);
        $this->pedido = PedidoBma::query()->create(['folio' => 'T02-PRUEBA', 'fecha' => now()->toDateString(), 'vendedor_id' => $this->vendedora->id,
            'cliente_id' => $cliente->id, 'departamento_operacion_id' => $depto->id, 'origen_id' => $origenPedido->id,
            'almacen_id' => $origen->id, 'sucursal_destino_id' => $sucursal->id,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase('EN_CEDIS')->id,
            'consulta_cerrada_at' => now(), 'pago_validado_at' => now(), 'numero_remision' => 'REM-T02',
            'total_mercancia' => 100, 'total_a_cobrar' => 100, 'costo_envio' => 0, 'saldo_a_favor' => 0, 'costo_seguro' => 0,
            'solicitud_preparacion_json' => ['version' => 1, 'modalidad_entrega' => 'tienda', 'area_revision' => 'control_pedidos',
                'codigo_modalidad' => 'RECOGE_TIENDA_TRANSFERENCIA', 'cobertura_tienda' => ['tipo' => 'transferencia'], 'atencion_tienda' => 'no']]);
        $this->pedido->documentos()->create(['tipo' => PedidoBmaDocumento::TIPO_REMISION, 'ruta_archivo' => 'remision.pdf', 'nombre_original' => 'remision.pdf', 'mime_type' => 'application/pdf', 'tamano_bytes' => 100, 'orden' => 1, 'activo' => true]);
    }

    private function iniciarConfirmacion(string $cobertura): void
    {
        config(['control_pedidos.direcciones_normalizadas' => true]);
        CatalogoEstatusPedido::query()->firstOrCreate(['fase_ciclo' => 'PESAJE_RESPONDIDO'], ['codigo_interno' => 'PESAJE_RESPONDIDO', 'nombre_visual' => 'Consulta cerrada', 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]);
        $this->pedido->documentos()->delete();
        $json = $this->pedido->solicitud_preparacion_json;
        $json['cobertura_tienda'] = ['tipo' => $cobertura];
        $json['codigo_modalidad'] = $cobertura === 'credito' ? 'RECOGE_TIENDA_CREDITO' : 'RECOGE_TIENDA_TRANSFERENCIA';
        $json['revision_disponibilidad'] = ['estado' => 'sin_excepciones'];
        $this->pedido->update(['catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase('PESAJE_RESPONDIDO')->id,
            'folio_remision' => 'PED-PRUEBA', 'numero_remision' => null, 'pago_validado_at' => null, 'solicitud_preparacion_json' => $json]);
        $this->pedido->origen->update(['requiere_logistica' => true]);
        $this->pedido->documentos()->create(['tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO, 'ruta_archivo' => 'pedido.pdf', 'nombre_original' => 'pedido.pdf', 'mime_type' => 'application/pdf', 'tamano_bytes' => 100, 'orden' => 0, 'activo' => true]);
        $this->auxiliar->givePermissionTo(Permission::findOrCreate('control_pedidos.auditar', 'web'));
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    }

    public function test_confirmar_consulta_cedis_resuelve_almacenes_sin_simular_recepcion(): void
    {
        $this->iniciarConfirmacion('credito');
        $origen = $this->pedido->almacen;
        $tipo = CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'CEDIS'], ['activo' => true]);
        $origen->update(['tipo_almacen_id' => $tipo->id, 'permite_salida_pedidos' => true]);
        $this->pedido->update(['almacen_id' => null]);
        $this->destino->update(['permite_salida_pedidos' => false]);

        $this->actingAs($this->vendedora)->putJson(route('control_pedidos.enviar', $this->pedido))
            ->assertOk()->assertJsonPath('estatus.fase_ciclo', 'PENDIENTE_AUXILIAR');
        $fresco = $this->pedido->fresh();
        $this->assertSame($origen->id, $fresco->almacen_id);
        $this->assertSame($this->destino->id, $fresco->solicitud_preparacion_json['ruta_almacenes_tienda']['almacen_destino_id']);
        $this->assertNull($fresco->pago_validado_at);
        $this->assertSame(0, $fresco->resguardosPdv()->count());
        $fusion = app(\App\Services\ControlPedidos\FusionarSolicitudPreparacionPedido::class)->ejecutar($fresco->solicitud_preparacion_json, ['modalidad_entrega' => 'tienda']);
        $this->assertSame($fresco->solicitud_preparacion_json['ruta_almacenes_tienda'], $fusion['ruta_almacenes_tienda']);
    }

    public function test_confirmar_sin_cedis_configurado_no_adivina_origen_ni_avanza(): void
    {
        $this->iniciarConfirmacion('credito');
        $this->pedido->update(['almacen_id' => null]);
        $this->actingAs($this->vendedora)->putJson(route('control_pedidos.enviar', $this->pedido))
            ->assertUnprocessable()->assertJsonValidationErrors('almacen_id');
        $this->assertNull($this->pedido->fresh()->almacen_id);
        $this->assertSame('PESAJE_RESPONDIDO', $this->pedido->fresh()->estatus->fase_ciclo);
    }

    public function test_resolucion_cedis_no_modifica_captura_de_envios(): void
    {
        $json = $this->pedido->solicitud_preparacion_json;
        $json['modalidad_entrega'] = 'nacional';
        $this->pedido->update(['almacen_id' => null, 'solicitud_preparacion_json' => $json]);
        app(\App\Services\ControlPedidos\ResolverAlmacenesConsultaCedisService::class)->ejecutar($this->pedido);
        $this->assertNull($this->pedido->fresh()->almacen_id);
        $this->assertArrayNotHasKey('ruta_almacenes_tienda', $this->pedido->fresh()->solicitud_preparacion_json);
    }

    public function test_autoguardar_destino_de_consulta_cedis_conserva_origen_y_no_crea_custodia(): void
    {
        $this->iniciarConfirmacion('transferencia');
        $json = $this->pedido->solicitud_preparacion_json;
        unset($json['cobertura_tienda'], $json['codigo_modalidad']);
        $this->pedido->update(['solicitud_preparacion_json' => $json, 'consulta_cerrada_at' => null]);
        $origenId = $this->pedido->almacen_id;
        $otra = Sucursal::factory()->create(['activo' => true]);
        $this->actingAs($this->vendedora)->postJson('/control-pedidos/autoguardar', [
            'pedido_id' => $this->pedido->id,
            'revision_esperada' => (int) $this->pedido->revision_registro,
            'base_captura' => \App\Support\ControlPedidos\RevisionCapturaPedido::base($this->pedido->fresh()),
            'sucursal_destino_id' => $otra->id,
        ])->assertOk()->assertJsonPath('sucursal_destino_id', $otra->id);
        $this->assertSame($origenId, $this->pedido->fresh()->almacen_id);
        $this->assertSame(0, $this->pedido->resguardosPdv()->count());
    }

    public function test_confirmar_transferencia_exige_pago_y_destino_y_llega_a_auditoria_sin_pedir_logistica(): void
    {
        $this->iniciarConfirmacion('transferencia');
        $this->actingAs($this->vendedora)->putJson(route('control_pedidos.enviar', $this->pedido))->assertStatus(422);
        app(\App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService::class)->handle($this->pedido->fresh(), [
            'monto' => 100, 'forma_pago' => 'transferencia', 'catalogo_banco_id' => \App\Models\CatalogoBanco::query()->create(['nombre' => 'Banco prueba', 'activo' => true, 'compartido' => true])->id, 'idempotency_key' => 'pago-confirmacion',
        ], UploadedFile::fake()->create('transferencia.pdf', 10, 'application/pdf'), $this->vendedora->id);
        $destinoId = $this->pedido->sucursal_destino_id;
        $this->pedido->update(['sucursal_destino_id' => null]);
        $this->actingAs($this->vendedora)->putJson(route('control_pedidos.enviar', $this->pedido))->assertStatus(422);
        $this->actingAs($this->vendedora)->postJson(route('control_pedidos.sucursal_destino', $this->pedido), ['sucursal_destino_id' => $destinoId])
            ->assertOk()->assertJsonPath('sucursal_destino_id', $destinoId);
        $this->actingAs($this->vendedora)->putJson(route('control_pedidos.enviar', $this->pedido))
            ->assertOk()->assertJsonPath('estatus.fase_ciclo', 'PENDIENTE_AUXILIAR');
        $this->assertNull($this->pedido->fresh()->pago_validado_at);
        $this->assertSame(0, $this->pedido->resguardosPdv()->count());
        $this->actingAs($this->auxiliar)->post(route('control_pedidos.auditar.credito_tienda', $this->pedido), [
            'decision_auditoria' => true, 'resultado' => 'aprobado',
        ])->assertRedirect()->assertSessionHas('error', 'Este pedido no está en cobertura de crédito.');
        $this->assertNull($this->pedido->fresh()->pago_validado_at);
        $this->post(route('control_pedidos.auditar.validar_pago', $this->pedido))
            ->assertRedirect()->assertSessionHas('success', 'Pago validado correctamente.');
        app(GestionarRemisionPedidoBmaService::class)->actualizarNumeroRemision($this->pedido->fresh(), 'REM-CONFIRMADA', $this->auxiliar->id);
        app(GestionarRemisionPedidoBmaService::class)->subir($this->pedido->fresh(), UploadedFile::fake()->create('remision.pdf', 10, 'application/pdf'), $this->auxiliar->id, now()->toDateString());
        app(\App\Services\ControlPedidos\AprobarPedidoBmaService::class)->ejecutar($this->pedido->fresh(), $this->auxiliar->id);
        $this->preparar();
        $this->operar('salida', $this->cedis);
        $this->assertSame(ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $this->pedido->resguardosPdv()->firstOrFail()->estado);
    }

    public function test_credito_cedis_va_a_revision_sin_transferencia_y_completa_custodia_y_entrega(): void
    {
        $this->iniciarConfirmacion('credito');
        $this->actingAs($this->vendedora)->putJson(route('control_pedidos.enviar', $this->pedido))
            ->assertOk()->assertJsonPath('estatus.fase_ciclo', 'PENDIENTE_AUXILIAR');
        $this->assertSame(0, $this->pedido->pagosExhibicion()->count());
        app(\App\Services\ControlPedidos\RegistrarResultadoCreditoTiendaService::class)->ejecutar($this->pedido->fresh(), $this->auxiliar, 'bloqueado', 'Regularizar en Wizerp');
        $this->assertFalse(app(TransferenciaTiendaService::class)->resumen($this->pedido->fresh(), $this->cedis)['puede_preparar']);
        try {
            app(\App\Services\ControlPedidos\ValidarPagoPedidoBmaService::class)->ejecutar($this->pedido->fresh(), $this->auxiliar->id);
            $this->fail('El crédito bloqueado no se valida.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Wizerp', $e->getMessage());
        }
        $rutaCredito = route('control_pedidos.auditar.credito_tienda', $this->pedido);
        $decision = ['decision_auditoria' => true, 'resultado' => 'aprobado'];
        $this->post($rutaCredito, $decision)->assertForbidden();
        $this->actingAs($this->auxiliar)->post($rutaCredito, $decision)
            ->assertRedirect()->assertSessionHas('success', 'Crédito aprobado y validado.');
        $validado = $this->pedido->fresh();
        $this->assertNotNull($validado->pago_validado_at);
        $this->assertSame($this->auxiliar->id, $validado->pago_validado_por_id);
        $this->assertNull($validado->numero_remision);
        $this->assertSame(PedidoBmaPago::REVISION_VERIFICADO, $validado->pagosExhibicion()->sole()->estado_revision);

        $historial = $validado->historial()->count();
        $this->post($rutaCredito, $decision)->assertRedirect()->assertSessionHas('success');
        $this->assertSame($historial, $validado->historial()->count());
        $this->post($rutaCredito, ['decision_auditoria' => true, 'resultado' => 'vencido'])
            ->assertRedirect()->assertSessionHas('error', 'El crédito de este pedido ya fue aprobado y validado.');

        $this->getJson(route('control_pedidos.auditar.listado', ['tab' => 'PENDIENTES']))->assertOk()
            ->assertJsonPath('pedidos.data.0.id', $this->pedido->id)
            ->assertJsonPath('pedidos.data.0.hito_auditoria', 'pendiente_remision')
            ->assertJsonPath('pedidos.data.0.solicitud_preparacion.credito_wizerp.resultado', 'aprobado');

        app(GestionarRemisionPedidoBmaService::class)->actualizarNumeroRemision($this->pedido->fresh(), 'REM-CREDITO', $this->auxiliar->id);
        app(GestionarRemisionPedidoBmaService::class)->subir($this->pedido->fresh(), UploadedFile::fake()->create('credito.pdf', 10, 'application/pdf'), $this->auxiliar->id);
        app(\App\Services\ControlPedidos\AprobarPedidoBmaService::class)->ejecutar($this->pedido->fresh(), $this->auxiliar->id);
        $this->actingAs($this->cedis)->getJson(route('control_pedidos.cedis.listado', ['tab' => 'PENDIENTES']))->assertOk()
            ->assertJsonPath('pedidos.data.0.id', $this->pedido->id);
        $this->assertSame('T-09', $this->preparar()['flujo']);
        $this->operar('salida', $this->cedis);
        $this->assertSame(ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $this->pedido->resguardosPdv()->firstOrFail()->estado);
        $this->operar('recibir', $this->auxiliar, ['bolsas_verificadas' => true]);
        $this->operar('custodia', $this->auxiliar, ['almacen_id' => $this->destino->id, 'ubicacion' => 'Anaquel crédito']);
        $this->assertSame('entregada', $this->operar('entregar', $this->auxiliar, ['receptor' => 'Cliente crédito', 'foto' => UploadedFile::fake()->image('entrega-credito.jpg')])['estado']);
        $this->assertSame(['credito'], $this->pedido->pagosExhibicion()->pluck('forma_pago')->all());
    }

    public function test_auditoria_revierte_la_aprobacion_de_credito_si_falla_la_validacion(): void
    {
        $this->iniciarConfirmacion('credito');
        $this->actingAs($this->vendedora)->putJson(route('control_pedidos.enviar', $this->pedido))
            ->assertOk()->assertJsonPath('estatus.fase_ciclo', 'PENDIENTE_AUXILIAR');
        $this->pedido->pagosExhibicion()->create([
            'numero_exhibicion' => 1, 'monto' => 100, 'forma_pago' => 'transferencia', 'fecha_pago' => now(),
            'estado_revision' => PedidoBmaPago::REVISION_RECHAZADO, 'activo_para_cobertura' => false,
        ]);
        $antes = $this->pedido->fresh();
        $historial = $antes->historial()->count();

        $this->actingAs($this->auxiliar)->post(route('control_pedidos.auditar.credito_tienda', $this->pedido), [
            'decision_auditoria' => true, 'resultado' => 'aprobado',
        ])->assertRedirect()->assertSessionHas('error', 'Hay comprobantes rechazados pendientes de sustitución.');

        $despues = $this->pedido->fresh();
        $this->assertSame($antes->solicitud_preparacion_json, $despues->solicitud_preparacion_json);
        $this->assertNull($despues->pago_validado_at);
        $this->assertSame(PedidoBmaPago::REVISION_RECHAZADO, $despues->pagosExhibicion()->sole()->estado_revision);
        $this->assertSame($historial, $despues->historial()->count());
    }

    public function test_credito_vencido_regresa_el_pedido_con_la_indicacion(): void
    {
        $this->iniciarConfirmacion('credito');
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => 'RECHAZADO_VENDEDORA'],
            ['codigo_interno' => 'RECHAZADO_VENDEDORA', 'nombre_visual' => 'Devuelto para corrección', 'color_hex' => '#999999', 'orden' => 8, 'activo' => true]
        );
        $this->actingAs($this->vendedora)->putJson(route('control_pedidos.enviar', $this->pedido))
            ->assertOk()->assertJsonPath('estatus.fase_ciclo', 'PENDIENTE_AUXILIAR');

        $causa = 'Vencimiento confirmado en Wizerp.';
        $this->actingAs($this->auxiliar)->post(route('control_pedidos.auditar.credito_tienda', $this->pedido), [
            'decision_auditoria' => true, 'resultado' => 'vencido', 'causa' => $causa,
        ])->assertRedirect()->assertSessionHas('success', 'Pedido devuelto a Ventas por crédito vencido.');
        $devuelto = $this->pedido->fresh();
        $this->assertSame('RECHAZADO_VENDEDORA', $devuelto->estatus->fase_ciclo);
        $this->assertSame(DevolverCreditoVencidoTiendaService::INDICACION.' '.$causa, $devuelto->motivo_rechazo);
        $this->assertSame('vencido', $devuelto->solicitud_preparacion_json['credito_wizerp']['resultado']);
        $this->getJson(route('control_pedidos.auditar.detalle', $this->pedido))->assertOk()
            ->assertJsonPath('pedido.solicitud_preparacion.credito_wizerp.causa', $devuelto->motivo_rechazo);
        $this->getJson(route('control_pedidos.auditar.listado', ['tab' => 'RECHAZADOS']))->assertOk()
            ->assertJsonPath('pedidos.data.0.solicitud_preparacion.credito_wizerp.resultado', 'vencido');
        Notification::assertSentTo($this->vendedora, AlertaPedidoBma::class, fn ($alerta) => $alerta->tipoAlerta === 'pedido_rechazado_auxiliar'
            && str_contains($alerta->mensaje, $causa));
    }

    public function test_credito_pagado_vuelve_a_revision_sin_exigir_evidencia(): void
    {
        $this->iniciarConfirmacion('credito');
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => 'RECHAZADO_VENDEDORA'],
            ['codigo_interno' => 'RECHAZADO_VENDEDORA', 'nombre_visual' => 'Devuelto para corrección', 'color_hex' => '#999999', 'orden' => 8, 'activo' => true]
        );
        $this->actingAs($this->vendedora)->putJson(route('control_pedidos.enviar', $this->pedido))->assertOk();
        app(\App\Services\ControlPedidos\DevolverCreditoVencidoTiendaService::class)
            ->ejecutar($this->pedido->fresh(), $this->auxiliar);

        $this->actingAs($this->vendedora)
            ->postJson(route('control_pedidos.credito_pagado', $this->pedido))
            ->assertOk()
            ->assertJsonPath('estatus.fase_ciclo', 'PENDIENTE_AUXILIAR')
            ->assertJsonPath('solicitud_preparacion.credito_wizerp.resultado', 'pagado')
            ->assertJsonPath('motivo_rechazo', null);
        $this->assertSame(0, $this->pedido->documentos()->where('comentario', 'Crédito pagado')->count());
    }

    private function operar(string $accion, User $actor, array $datos = []): array
    {
        $version = $this->pedido->fresh()->solicitud_preparacion_json['transferencia_tienda']['version'] ?? 0;

        return app(TransferenciaTiendaService::class)->ejecutar($this->pedido, $actor, $accion, array_merge(['version' => $version], $datos));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function activarMd(bool $oportuno = true): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 14:00:00', 'America/Mexico_City'));
        $json = $this->pedido->fresh()->solicitud_preparacion_json;
        $json['atencion_tienda'] = 'prevista';
        $json['tienda_md_solicitud'] = ['fecha' => '2026-11-04'];
        $json['tienda_md_reporte'] = ['estado' => $oportuno ? 'oportuno' : 'tardio', 'oportuno' => $oportuno,
            'primer_pago_at' => $oportuno ? '2026-11-04T12:59:00-06:00' : '2026-11-04T13:01:00-06:00'];
        $this->pedido->update(['solicitud_preparacion_json' => $json, 'prioridad_md' => $oportuno]);
    }

    public function test_md_oportuno_se_traslada_y_conserva_custodia_pagada_si_no_recoge_hoy(): void
    {
        $this->activarMd();
        $this->assertSame('T-02.1', $this->preparar()['flujo']);
        $this->operar('salida', $this->cedis);
        $this->operar('recibir', $this->auxiliar, ['bolsas_verificadas' => true]);
        $this->operar('custodia', $this->auxiliar, ['almacen_id' => $this->destino->id, 'ubicacion' => 'MD, anaquel B']);
        Carbon::setTestNow(Carbon::parse('2026-11-05 10:00:00', 'America/Mexico_City'));
        $estado = app(TransferenciaTiendaService::class)->resumen($this->pedido->fresh(), $this->auxiliar);
        $this->assertSame('en_custodia', $estado['estado']);
        $this->assertTrue($estado['puede_entregar']);
        $this->assertNull($estado['limite_recoleccion']);
        $this->assertSame('entregada', $this->operar('entregar', $this->auxiliar, ['receptor' => 'Cliente MD', 'foto' => UploadedFile::fake()->image('md-entrega.jpg')])['estado']);
    }

    public function test_md_tardio_no_prepara_y_espera_acuerdo_explicito_para_continuar_t02(): void
    {
        $this->activarMd(false);
        $resumen = app(TransferenciaTiendaService::class)->resumen($this->pedido->fresh(), $this->cedis);
        $this->assertFalse($resumen['puede_preparar']);
        $this->assertTrue($resumen['atencion_md']['pendiente_acuerdo']);
        $this->assertSame('prevista', $this->pedido->fresh()->solicitud_preparacion_json['atencion_tienda']);
        $original = $this->pedido->fresh()->solicitud_preparacion_json['tienda_md_reporte'];
        $resultado = $this->operar('continuar_posterior', $this->vendedora, ['observaciones' => 'Cliente acepta recoger mañana en la misma sucursal.']);
        $this->assertSame('T-02', $resultado['flujo']);
        $this->assertSame($original, $this->pedido->fresh()->solicitud_preparacion_json['tienda_md_reporte']);
        $this->assertSame('REM-T02', $this->pedido->fresh()->numero_remision);
        $this->assertFalse($this->pedido->fresh()->prioridad_md);
        $this->assertSame('preparada', $this->preparar()['estado']);
    }

    public function test_md_tardio_no_se_prepara_por_la_ruta_de_empaque_anterior(): void
    {
        $this->activarMd(false);
        $this->expectException(\RuntimeException::class);
        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar($this->pedido->fresh(), $this->cedis->id);
    }

    public function test_md_reportado_en_otro_dia_no_se_reprograma_solo(): void
    {
        $this->activarMd();
        Carbon::setTestNow(Carbon::parse('2026-11-05 10:00:00', 'America/Mexico_City'));
        $resumen = app(TransferenciaTiendaService::class)->resumen($this->pedido->fresh(), $this->cedis);
        $this->assertFalse($resumen['puede_preparar']);
        $this->assertTrue($resumen['atencion_md']['pendiente_acuerdo']);
        $this->assertSame('T-02.1', $resumen['flujo']);
    }

    public function test_cedis_no_puede_acordar_la_continuacion_por_la_vendedora(): void
    {
        $this->activarMd(false);
        $this->expectException(ValidationException::class);
        $this->operar('continuar_posterior', $this->cedis, ['observaciones' => 'Se pretende continuar sin vendedora.']);
    }

    private function preparar(): array
    {
        return $this->operar('preparar', $this->cedis, ['bultos' => [['foto_bulto' => UploadedFile::fake()->image('bolsa.jpg')]], 'observaciones' => 'Pedido completo, bolsa identificada.']);
    }

    public function test_recorrido_separa_preparacion_salida_recepcion_custodia_y_entrega(): void
    {
        $origen = $this->pedido->almacen_id;
        $this->assertSame('preparada', $this->preparar()['estado']);
        $this->assertSame(0, ResguardoPdv::query()->count());
        $this->assertSame('en_traslado', $this->operar('salida', $this->cedis)['estado']);
        $this->assertNull(ResguardoPdv::query()->first()->custodia_confirmada_at);
        $this->assertSame('recibida', $this->operar('recibir', $this->auxiliar, ['bolsas_verificadas' => true])['estado']);
        $this->assertSame('en_custodia', $this->operar('custodia', $this->auxiliar, ['almacen_id' => $this->destino->id, 'ubicacion' => 'Recepción, anaquel B, REM-T02'])['estado']);
        $this->assertSame($origen, $this->pedido->fresh()->almacen_id);
        $this->assertSame($this->destino->id, ResguardoPdv::query()->first()->almacen_id);
        $this->assertSame('entregada', $this->operar('entregar', $this->auxiliar, ['receptor' => 'Cliente titular', 'foto' => UploadedFile::fake()->image('entrega.jpg')])['estado']);
        $this->assertSame('ENTREGADO', $this->pedido->fresh()->estatus->fase_ciclo);
        $this->assertSame('entregada', $this->operar('entregar', $this->auxiliar, ['receptor' => 'Cliente titular', 'foto' => UploadedFile::fake()->image('reintento.jpg')])['estado']);
        $this->assertSame(1, ResguardoPdvEntrega::query()->count());
        $this->assertSame(0, ResguardoPdvEvidencia::query()->where('tipo', 'firma')->count());
        $this->assertSame(1, ResguardoPdvEvidencia::query()->where('tipo', 'foto')->count());
        $this->assertSame(0, PedidoBmaPago::query()->count());
    }

    public function test_no_prepara_sin_ingreso_verificado(): void
    {
        $this->pedido->update(['pago_validado_at' => null]);
        $this->expectException(ValidationException::class);
        $this->preparar();
    }

    public function test_no_recibe_antes_de_la_salida(): void
    {
        $this->preparar();
        $this->expectException(ValidationException::class);
        $this->operar('recibir', $this->auxiliar, ['bolsas_verificadas' => true]);
    }

    public function test_otra_sucursal_no_puede_recibir(): void
    {
        $this->preparar();
        $this->operar('salida', $this->cedis);
        $ajena = User::factory()->create();
        $ajena->givePermissionTo(Permission::findOrCreate('control_pedidos.tienda.responder', 'web'));
        $this->expectException(HttpException::class);
        $this->operar('recibir', $ajena, ['bolsas_verificadas' => true]);
    }

    public function test_version_obsoleta_no_registra_custodia(): void
    {
        $this->preparar();
        $this->operar('salida', $this->cedis);
        $this->operar('recibir', $this->auxiliar, ['bolsas_verificadas' => true]);
        $this->expectException(ValidationException::class);
        app(TransferenciaTiendaService::class)->ejecutar($this->pedido, $this->auxiliar, 'custodia', ['version' => 0, 'almacen_id' => $this->destino->id, 'ubicacion' => 'B']);
    }

    public function test_veinte_dias_solo_avisan_gestion_sin_liberar_pago_ni_custodia(): void
    {
        $this->preparar();
        $this->operar('salida', $this->cedis);
        $this->operar('recibir', $this->auxiliar, ['bolsas_verificadas' => true]);
        $this->operar('custodia', $this->auxiliar, ['almacen_id' => $this->destino->id, 'ubicacion' => 'B']);
        $json = $this->pedido->fresh()->solicitud_preparacion_json;
        $json['remision_tienda']['emitida_at'] = now()->subDays(21)->toIso8601String();
        $this->pedido->update(['solicitud_preparacion_json' => $json]);
        $this->assertSame(1, app(SeguimientoRecoleccionT02Service::class)->ejecutar());
        $this->assertSame(0, app(SeguimientoRecoleccionT02Service::class)->ejecutar());
        $this->assertSame('pendiente_gestion', $this->pedido->fresh()->solicitud_preparacion_json['seguimiento_recoleccion']['estado']);
        $this->assertNotNull($this->pedido->fresh()->pago_validado_at);
        $this->assertNull($this->pedido->fresh()->cancelado_at);
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, ResguardoPdv::query()->first()->estado);
        $this->assertFalse(ResguardoPdv::query()->first()->entrega_bloqueada);
    }

    public function test_desde_pdv_se_conserva_procedencia_y_se_recibe_en_la_misma_sucursal(): void
    {
        foreach (['control_pedidos.tienda.ver', 'control_pedidos.tienda.responder'] as $permiso) {
            $this->cedis->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        $json = $this->pedido->solicitud_preparacion_json;
        $json['area_revision'] = 'preparacion_tienda';
        $this->pedido->update(['almacen_id' => $this->destino->id, 'solicitud_preparacion_json' => $json]);
        PedidoBmaTareaPreparacion::query()->create(['pedido_bma_id' => $this->pedido->id,
            'catalogo_modalidad_preparacion_id' => CatalogoModalidadPreparacionPedido::query()->where('codigo', 'RECOGE_TIENDA_TRANSFERENCIA')->value('id'),
            'almacen_id' => $this->destino->id, 'estado' => 'RESPONDIDA', 'area_responsable_codigo' => 'TIENDA',
            'asignada_a_id' => $this->cedis->id, 'solicitada_por_id' => $this->vendedora->id, 'version' => 1]);
        $this->assertFalse($this->pedido->fresh()->esTrasladoInternoAPdv());
        $this->preparar();
        $this->assertSame('en_traslado', $this->operar('salida', $this->cedis)['estado']);
        $this->assertSame('recibida', $this->operar('recibir', $this->auxiliar, ['bolsas_verificadas' => true])['estado']);
        $this->assertSame($this->destino->id, $this->pedido->fresh()->almacen_id);
    }

    public function test_city_center_se_identifica_como_ramificacion_t03(): void
    {
        $this->pedido->sucursalDestino->update(['nombre' => 'City Center']);
        $resumen = app(TransferenciaTiendaService::class)->resumen($this->pedido->fresh(), $this->cedis);
        $this->assertSame('T-03', $resumen['derivacion']);
        $this->assertTrue($resumen['puede_preparar']);
        $this->preparar();
        $this->operar('salida', $this->cedis);
        $this->operar('recibir', $this->auxiliar, ['bolsas_verificadas' => true]);
        $this->assertSame('en_custodia', $this->operar('custodia', $this->auxiliar, ['almacen_id' => $this->destino->id, 'ubicacion' => 'City Center A'])['estado']);
    }

    public function test_la_emision_de_remision_se_conserva_al_sustituir_pdf(): void
    {
        $this->pedido->update(['catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase('PENDIENTE_AUXILIAR')->id]);
        $service = app(GestionarRemisionPedidoBmaService::class);
        $service->subir($this->pedido, UploadedFile::fake()->create('primera.pdf', 10, 'application/pdf'), $this->auxiliar->id, now()->subDays(3)->toDateString());
        $fecha = $this->pedido->fresh()->solicitud_preparacion_json['remision_tienda']['emitida_at'];
        $service->subir($this->pedido->fresh(), UploadedFile::fake()->create('sustituta.pdf', 10, 'application/pdf'), $this->auxiliar->id, now()->toDateString());
        $this->assertSame($fecha, $this->pedido->fresh()->solicitud_preparacion_json['remision_tienda']['emitida_at']);
    }

    private function custodiaInicial(): void
    {
        $this->preparar();
        $this->operar('salida', $this->cedis);
        $this->operar('recibir', $this->auxiliar, ['bolsas_verificadas' => true]);
        $this->operar('custodia', $this->auxiliar, ['almacen_id' => $this->destino->id, 'ubicacion' => 'Origen A']);
    }

    public function test_traslado_solicita_evidencia_o_anotacion_y_conserva_pago(): void
    {
        $this->custodiaInicial();
        $nueva = Sucursal::factory()->create(['activo' => true]);
        $url = '/control-pedidos/'.$this->pedido->id.'/movimientos-tienda/solicitar';
        $datos = ['version' => 0, 'sucursal_destino_id' => $nueva->id, 'motivo' => 'El cliente solicita otra sucursal'];
        $this->actingAs($this->vendedora)->postJson($url, $datos)->assertUnprocessable()->assertJsonValidationErrors('evidencias');
        $this->assertArrayNotHasKey('segundo_traslado', $this->pedido->fresh()->solicitud_preparacion_json);
        $this->postJson($url, $datos + ['error_captura' => true])->assertOk()->assertJsonPath('traslado.anotacion', 'Se notificó un error de captura');
        $this->assertSame($this->destino->sucursal_id, $this->pedido->fresh()->sucursal_destino_id);
        $this->assertSame('REM-T02', $this->pedido->fresh()->numero_remision);
        $this->postJson($url, $datos + ['error_captura' => true])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertSame(1, $this->pedido->historial()->where('comentarios', 'like', '%Se notificó un error de captura%')->count());
    }

    public function test_segundo_traslado_recorrido_completo_hasta_entrega_en_destino(): void
    {
        $this->custodiaInicial();
        $origenId = $this->destino->sucursal_id;
        $nueva = Sucursal::factory()->create(['activo' => true, 'nombre' => 'City Center']);
        $almacen = Almacen::query()->create(['nombre' => 'City Center PDV', 'codigo' => 'PDV-CC', 'tipo_almacen_id' => CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'PDV'], ['activo' => true])->id, 'sucursal_id' => $nueva->id, 'activo' => true]);
        $gerente = User::factory()->create(['departamento_id' => $this->vendedora->departamento_id]);
        $gerente->assignRole(Role::findOrCreate('Gerente', 'web'));
        $gerente->givePermissionTo(Permission::findOrCreate(SolicitarSegundoTrasladoTiendaService::PERMISO, 'web'));
        $destino = User::factory()->create();
        $destino->concederAccesoSucursal($nueva, true);
        foreach (['control_pedidos.tienda.responder', 'pdv.resguardos.entregar', 'pdv.acceder'] as $permiso) {
            $destino->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        $url = '/control-pedidos/'.$this->pedido->id.'/movimientos-tienda/';
        $this->actingAs($this->vendedora)->post($url.'solicitar', ['version' => 0, 'sucursal_destino_id' => $nueva->id, 'motivo' => 'Petición del cliente', 'evidencias' => [UploadedFile::fake()->image('cliente.jpg')]])->assertOk()->assertJsonCount(1, 'evidencias');
        $this->actingAs($this->auxiliar)->postJson($url.'autorizar', ['version' => 1, 'recorrido' => 'Ruta confirmada'])->assertUnprocessable();
        $this->actingAs($gerente)->postJson($url.'autorizar', ['version' => 1])->assertUnprocessable()->assertJsonValidationErrors('recorrido');
        $this->postJson($url.'autorizar', ['version' => 1, 'recorrido' => 'Origen a City Center, recorrido coordinado con logística'])->assertOk()->assertJsonPath('traslado.recepcion_confirmada', false);
        $this->assertSame($origenId, ResguardoPdv::query()->first()->sucursal_id);
        $this->assertNotNull($this->pedido->fresh()->motivoBloqueoEntregaTienda());
        $this->actingAs($destino)->postJson($url.'salida', ['version' => 2])->assertUnprocessable();
        $this->actingAs($this->auxiliar)->postJson($url.'salida', ['version' => 2])->assertOk()->assertJsonPath('traslado.movimiento_estado', 'en_camino');
        $this->postJson($url.'recepcion', ['version' => 3, 'bolsas_verificadas' => true, 'almacen_id' => $almacen->id, 'ubicacion' => 'City Center B'])->assertUnprocessable();
        $this->actingAs($destino)->postJson($url.'recepcion', ['version' => 3, 'bolsas_verificadas' => true, 'almacen_id' => $this->destino->id, 'ubicacion' => 'City Center B'])->assertUnprocessable();
        $this->postJson($url.'recepcion', ['version' => 3, 'bolsas_verificadas' => true, 'almacen_id' => $almacen->id, 'ubicacion' => 'City Center B'])->assertOk()->assertJsonPath('traslado.recepcion_confirmada', true);
        $resguardo = ResguardoPdv::query()->first();
        $this->assertSame($almacen->id, $resguardo->almacen_id);
        $this->assertSame('City Center B', $resguardo->snapshot_json['ubicacion_t02']);
        $this->assertNull($this->pedido->fresh()->motivoBloqueoEntregaTienda());
        $this->assertSame('entregada', $this->operar('entregar', $destino, ['receptor' => 'Cliente titular', 'foto' => UploadedFile::fake()->image('entrega-cc.jpg')])['estado']);
        $this->assertSame(1, ResguardoPdv::query()->count());
        $this->assertSame('REM-T02', $this->pedido->fresh()->numero_remision);
        $this->assertSame(0, PedidoBmaPago::query()->count());
    }

    public function test_modalidad_solo_registra_intencion_sin_convertir_ni_liberar_custodia(): void
    {
        $url = '/control-pedidos/'.$this->pedido->id.'/movimientos-tienda/modalidad';
        $datos = ['version' => 0, 'modalidad' => 'municipal', 'motivo' => 'El cliente pide envío a domicilio'];
        $this->actingAs($this->vendedora)->postJson($url, $datos)->assertUnprocessable();
        $this->custodiaInicial();
        $this->postJson($url, $datos)->assertOk()->assertJsonPath('modalidad.estado', 'pendiente_conexion');
        $this->assertSame('tienda', $this->pedido->fresh()->solicitud_preparacion_json['modalidad_entrega']);
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, ResguardoPdv::query()->first()->estado);
        $this->assertSame('REM-T02', $this->pedido->fresh()->numero_remision);
    }
    public function test_rechazo_conserva_origen_y_la_siguiente_solicitud_archiva_la_anterior(): void
    {
        $this->custodiaInicial();
        $origen = $this->pedido->sucursal_destino_id;
        $destino = Sucursal::factory()->create(['activo' => true]);
        $url = '/control-pedidos/'.$this->pedido->id.'/movimientos-tienda/';
        $datos = ['sucursal_destino_id' => $destino->id, 'motivo' => 'Corrección de captura', 'error_captura' => true];
        $this->actingAs($this->vendedora)->postJson($url.'solicitar', ['version' => 0] + $datos)->assertOk();
        $gerente = User::factory()->create();
        $gerente->assignRole(\Spatie\Permission\Models\Role::findOrCreate('Administrador', 'web'));
        $gerente->givePermissionTo(Permission::findOrCreate(\App\Services\ControlPedidos\SolicitarSegundoTrasladoTiendaService::PERMISO, 'web'));
        $this->actingAs($gerente)->postJson($url.'rechazar', ['version' => 1])->assertOk();
        $this->assertSame($origen, $this->pedido->fresh()->sucursal_destino_id);
        $this->assertSame($origen, ResguardoPdv::query()->first()->sucursal_id);
        $this->assertNull($this->pedido->fresh()->motivoBloqueoEntregaTienda());
        $this->actingAs($this->vendedora)->postJson($url.'solicitar', ['version' => 2] + $datos)->assertOk();
        $this->assertSame('rechazada', $this->pedido->fresh()->solicitud_preparacion_json['historial_traslados_tienda'][0]['estado']);
    }

    public function test_destino_antes_de_salir_conserva_documentos_y_no_solicita_segundo_traslado(): void
    {
        $this->preparar();
        $destino = Sucursal::factory()->create(['activo' => true]);
        $this->actingAs($this->vendedora)->postJson('/control-pedidos/'.$this->pedido->id.'/sucursal-destino', ['sucursal_destino_id' => $destino->id])->assertOk();
        $actual = $this->pedido->fresh();
        $this->assertSame($destino->id, $actual->sucursal_destino_id);
        $this->assertSame('REM-T02', $actual->numero_remision);
        $this->assertTrue($actual->tienePagoValidado());
        $this->assertArrayNotHasKey('segundo_traslado', $actual->solicitud_preparacion_json);
        $this->assertSame(0, ResguardoPdv::query()->count());
        $this->assertSame(1, $actual->bultosEmpaque()->count());
    }

    public function test_no_sale_a_sucursal_sin_almacen_de_custodia_configurado(): void
    {
        $this->preparar();
        $this->destino->update(['codigo' => 'GENERAL-CC']);
        $resumen = app(TransferenciaTiendaService::class)->resumen($this->pedido->fresh(), $this->cedis);
        $this->assertFalse($resumen['puede_salida']);
        $this->assertNotNull($resumen['bloqueo_destino']);
        try {
            app(\App\Services\ControlPedidos\MarcarEnviadoPedidoBmaService::class)->ejecutar($this->pedido, $this->cedis->id);
            $this->fail('No debe salir sin custodia configurada en destino.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('almacén PDV', $e->getMessage());
        }
        $this->assertSame(0, ResguardoPdv::query()->count());
    }

}
