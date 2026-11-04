<?php

namespace Tests\Feature\ControlPedidos;

use App\Exceptions\ControlPedidos\ConflictoCapturaPedido;
use App\Models\CatalogoBanco;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\SaldosAFavor\ActualizarPagoPedidoBmaService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use App\Services\SaldosAFavor\ResolverBancoCajaPdv;
use App\Services\SaldosAFavor\SustituirPagoPedidoBmaService;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use App\Support\Reportes\ClasificacionIngresoBancario;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ImportesPagoTiendaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        Storage::fake('public');
        $this->usuario = User::factory()->create();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR],
            ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
        );
    }

    public function test_el_autoguardado_devuelve_importes_y_conserva_la_cobertura(): void
    {
        $pedido = $this->pedido([
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'cobertura_tienda' => ['tipo' => 'apartado', 'confirmada' => false],
            ],
        ])->fresh();

        $respuesta = $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', [
            'pedido_id' => $pedido->id,
            'revision_esperada' => (int) $pedido->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($pedido),
            'total_mercancia' => 250,
        ])->assertOk();

        $respuesta->assertJsonPath('solicitud_preparacion.cobertura_tienda.tipo', 'apartado');
        $this->assertEquals(250, $respuesta->json('total_mercancia'));
        $this->assertArrayHasKey('saldo_a_favor', $respuesta->json());
        $this->assertArrayHasKey('saf_aplicaciones', $respuesta->json());
        $this->assertNotNull($respuesta->json('revision_registro'));
        $this->assertNotNull($respuesta->json('base_captura'));
        $this->assertSame('apartado', $pedido->fresh()->solicitud_preparacion_json['cobertura_tienda']['tipo']);
    }

    public function test_la_lectura_de_pagos_incluye_solicitud_e_importes(): void
    {
        $pedido = $this->pedido();

        $this->actingAs($this->usuario)
            ->getJson("/control-pedidos/{$pedido->id}/pagos")
            ->assertOk()
            ->assertJsonPath('solicitud_preparacion.cobertura_tienda.tipo', 'apartado')
            ->assertJsonPath('caja_pdv.nombre', 'Caja PDV')
            ->assertJsonPath('caja_pdv.disponible', false)
            ->assertJsonStructure([
                'total_mercancia',
                'saldo_a_favor',
                'saf_aplicaciones',
                'revision_registro',
                'base_captura',
                'prioridad_md',
                'resumen',
            ]);
    }

    public function test_un_cobro_en_tienda_conserva_el_medio_y_usa_caja_pdv(): void
    {
        $caja = $this->caja();
        $pedido = $this->pedido();
        $pago = $this->pagar($pedido, ['monto' => 40, 'forma_pago' => 'tarjeta', 'referencia' => 'TAR-1']);

        $this->assertSame('tarjeta', $pago->forma_pago);
        $this->assertSame($caja->id, (int) $pago->catalogo_banco_id);
        $this->assertSame(
            ClasificacionIngresoBancario::INGRESO_BANCARIO,
            ClasificacionIngresoBancario::clasificacionForma($pago->forma_pago)
        );
        $this->assertNotSame('transferencia', $pago->forma_pago);
        $json = $pedido->fresh()->solicitud_preparacion_json;
        $this->assertArrayNotHasKey('tienda_md', $json);
        $this->assertArrayNotHasKey('tienda_md_reporte', $json);

        $efectivo = $this->pagar($pedido->fresh(), ['monto' => 10, 'forma_pago' => 'efectivo', 'referencia' => 'EFE-1']);
        $this->assertSame('efectivo', $efectivo->forma_pago);
        $this->assertSame($caja->id, (int) $efectivo->catalogo_banco_id);
        $this->assertSame(
            ClasificacionIngresoBancario::PAGO_NO_BANCARIO,
            ClasificacionIngresoBancario::clasificacionForma($efectivo->forma_pago)
        );
        $this->assertEquals(40, $pago->fresh()->monto);
    }

    public function test_sin_caja_pdv_no_guarda_el_cobro_ni_elige_otro_banco(): void
    {
        $pedido = $this->pedido();
        $antes = PedidoBmaPago::query()->count();

        try {
            $this->pagar($pedido, ['monto' => 10, 'forma_pago' => 'efectivo', 'referencia' => 'SIN-CAJA']);
            $this->fail('Debió informar que falta Caja PDV.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(ResolverBancoCajaPdv::ERROR_CONFIGURACION, $e->getMessage());
        }

        $this->assertSame($antes, PedidoBmaPago::query()->count());
        $this->assertNull(PedidoBmaPago::query()->where('pedido_bma_id', $pedido->id)->value('catalogo_banco_id'));
    }

    public function test_la_transferencia_de_apartado_no_se_convierte_en_caja_pdv(): void
    {
        $this->caja();
        $pedido = $this->pedido();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(ResolverBancoCajaPdv::ERROR_TRANSFERENCIA);
        $this->pagar($pedido, [
            'monto' => 10,
            'forma_pago' => 'transferencia',
            'catalogo_banco_id' => CatalogoBanco::query()->where('nombre', 'BBVA')->value('id'),
            'referencia' => 'SPEI-AP',
        ]);
    }

    public function test_una_transferencia_ordinaria_usa_el_banco_elegido_y_no_etiqueta_md(): void
    {
        $this->caja();
        $bbva = CatalogoBanco::query()->where('nombre', 'BBVA')->firstOrFail();
        $pedido = $this->pedido([
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'cobertura_tienda' => ['tipo' => 'transferencia', 'confirmada' => false],
            ],
        ]);

        $pago = $this->pagar($pedido, [
            'monto' => 80,
            'forma_pago' => 'transferencia',
            'catalogo_banco_id' => $bbva->id,
            'referencia' => 'SPEI-80',
        ]);

        $this->assertSame($bbva->id, (int) $pago->catalogo_banco_id);
        $this->assertSame('transferencia', $pago->forma_pago);
        $json = $pedido->fresh()->solicitud_preparacion_json;
        $this->assertArrayNotHasKey('tienda_md', $json);
        $this->assertArrayNotHasKey('tienda_md_reporte', $json);
    }

    public function test_un_banco_fuera_de_alcance_no_se_acepta_en_transferencia(): void
    {
        $ajeno = CatalogoBanco::query()->create([
            'nombre' => 'Banco ajeno '.uniqid(),
            'activo' => true,
            'compartido' => false,
        ]);
        $pedido = $this->pedido([
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'cobertura_tienda' => ['tipo' => 'transferencia', 'confirmada' => false],
            ],
        ]);

        $this->expectException(ValidationException::class);
        $this->pagar($pedido, [
            'monto' => 20,
            'forma_pago' => 'transferencia',
            'catalogo_banco_id' => $ajeno->id,
            'referencia' => 'SPEI-AJENO',
        ]);
    }

    public function test_el_corte_md_solo_se_escribe_con_intencion_y_el_segundo_pago_no_lo_mueve(): void
    {
        $bbva = CatalogoBanco::query()->where('nombre', 'BBVA')->firstOrFail();
        $pedido = $this->pedido([
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'atencion_tienda' => 'prevista',
                'cobertura_tienda' => ['tipo' => 'transferencia', 'confirmada' => false],
            ],
        ]);

        $primero = $this->pagar($pedido, [
            'monto' => 30,
            'forma_pago' => 'transferencia',
            'catalogo_banco_id' => $bbva->id,
            'referencia' => 'MD-1',
        ]);
        $reporte = $pedido->fresh()->solicitud_preparacion_json['tienda_md_reporte'];
        $this->assertSame($primero->id, $reporte['primer_pago_id']);
        $this->assertFalse($reporte['confirmada']);

        $this->pagar($pedido->fresh(), [
            'monto' => 20,
            'forma_pago' => 'transferencia',
            'catalogo_banco_id' => $bbva->id,
            'referencia' => 'MD-2',
        ]);
        $siguiente = $pedido->fresh()->solicitud_preparacion_json['tienda_md_reporte'];
        $this->assertSame($primero->id, $siguiente['primer_pago_id']);
        $this->assertSame($reporte['primer_pago_at'], $siguiente['primer_pago_at']);
        $this->assertEquals(30, $primero->fresh()->monto);
    }

    public function test_el_credito_no_crea_exhibicion_de_caja_ni_etiqueta_md(): void
    {
        $this->caja();
        $pedido = $this->pedido([
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'cobertura_tienda' => ['tipo' => 'credito', 'confirmada' => false],
            ],
        ]);

        $pago = app(RegistrarPagoPedidoBmaService::class)->handle($pedido, [
            'monto' => 15,
            'forma_pago' => 'credito',
            'referencia' => 'CRED-1',
        ], null, $this->usuario->id);

        $this->assertSame('credito', $pago->forma_pago);
        $this->assertNull($pago->catalogo_banco_id);
        $this->assertArrayNotHasKey('tienda_md', $pedido->fresh()->solicitud_preparacion_json);
    }

    public function test_una_version_financiera_vieja_bloquea_el_pago_y_el_reintento_no_duplica(): void
    {
        $this->caja();
        $pedido = $this->pedido();
        $pagos = app(RegistrarPagoPedidoBmaService::class);

        try {
            $pagos->handle($pedido, [
                'monto' => 25,
                'forma_pago' => 'efectivo',
                'referencia' => 'VER-1',
                'idempotency_key' => 'abono-1',
                'revision_esperada' => ((int) $pedido->revision_registro) + 4,
                'total_mercancia_confirmado' => 100,
                'saldo_a_favor_confirmado' => 0,
            ], UploadedFile::fake()->create('ver.pdf', 20, 'application/pdf'), $this->usuario->id);
            $this->fail('Debió exigir datos vigentes.');
        } catch (ConflictoCapturaPedido $e) {
            $this->assertStringContainsString('Actualice el pedido', $e->getMessage());
        }
        $this->assertSame(0, PedidoBmaPago::query()->where('pedido_bma_id', $pedido->id)->count());

        $primero = $pagos->handle($pedido->fresh(), [
            'monto' => 25,
            'forma_pago' => 'efectivo',
            'referencia' => 'VER-1',
            'idempotency_key' => 'abono-1',
            'revision_esperada' => (int) $pedido->revision_registro,
            'total_mercancia_confirmado' => 100,
            'saldo_a_favor_confirmado' => 0,
        ], UploadedFile::fake()->create('ver-b.pdf', 20, 'application/pdf'), $this->usuario->id);

        $reintento = $pagos->handle($pedido->fresh(), [
            'monto' => 90,
            'forma_pago' => 'tarjeta',
            'referencia' => 'VER-OTRA',
            'idempotency_key' => 'abono-1',
            'revision_esperada' => 999,
            'total_mercancia_confirmado' => 1,
        ], UploadedFile::fake()->create('ver-c.pdf', 20, 'application/pdf'), $this->usuario->id);

        $this->assertSame($primero->id, $reintento->id);
        $this->assertSame(1, PedidoBmaPago::query()->where('pedido_bma_id', $pedido->id)->count());
        $this->assertEquals(25, $primero->fresh()->monto);
        $this->assertSame('efectivo', $primero->fresh()->forma_pago);
    }

    public function test_el_segundo_abono_actualiza_el_pendiente_sin_cambiar_el_primero(): void
    {
        $this->caja();
        $pedido = $this->pedido(['total_mercancia' => 100, 'total_a_cobrar' => 100]);
        $primero = $this->pagar($pedido, ['monto' => 40, 'forma_pago' => 'efectivo', 'referencia' => 'P1']);
        $this->pagar($pedido->fresh(), ['monto' => 70, 'forma_pago' => 'tarjeta', 'referencia' => 'P2']);

        $resumen = app(RegistrarPagoPedidoBmaService::class)->resumenPago($pedido->fresh());
        $this->assertEquals(40, $primero->fresh()->monto);
        $this->assertSame(2, PedidoBmaPago::query()->where('pedido_bma_id', $pedido->id)->where('activo_para_cobertura', true)->count());
        $this->assertEquals(0, $resumen['pendiente']);
        $this->assertGreaterThan(0, (float) $resumen['excedente']);
    }

    public function test_editar_y_sustituir_conservan_caja_pdv_y_el_medio(): void
    {
        $caja = $this->caja();
        $pedido = $this->pedido();
        $pago = $this->pagar($pedido, ['monto' => 40, 'forma_pago' => 'efectivo', 'referencia' => 'EDI-1']);

        $editado = app(ActualizarPagoPedidoBmaService::class)->handle(
            $pago,
            ['monto' => 40, 'forma_pago' => 'tarjeta', 'referencia' => 'EDI-1'],
            null,
            $this->usuario->id
        );
        $this->assertSame('tarjeta', $editado->forma_pago);
        $this->assertSame($caja->id, (int) $editado->catalogo_banco_id);

        $editado->update([
            'estado_revision' => PedidoBmaPago::REVISION_RECHAZADO,
            'activo_para_cobertura' => true,
        ]);
        $sustituto = app(SustituirPagoPedidoBmaService::class)->ejecutar(
            $editado->fresh(),
            ['monto' => 40, 'forma_pago' => 'deposito'],
            UploadedFile::fake()->create('sustituto.pdf', 20, 'application/pdf'),
            $this->usuario->id
        );
        $this->assertSame('deposito', $sustituto->forma_pago);
        $this->assertSame($caja->id, (int) $sustituto->catalogo_banco_id);
        $this->assertEquals(40, $pago->fresh()->monto);
    }

    public function test_md_oportuno_se_confirma_con_pago_y_remision_sin_recalcular_el_corte(): void
    {
        $pedido = $this->pedido(['solicitud_preparacion_json' => [
            'modalidad_entrega' => 'tienda', 'cobertura_tienda' => ['tipo' => 'transferencia'], 'atencion_tienda' => 'prevista',
            'tienda_md_reporte' => ['estado' => 'oportuno', 'oportuno' => true, 'primer_pago_at' => '2026-11-04T18:59:00Z'],
        ]]);
        $this->assertFalse(\App\Support\ControlPedidos\AtencionMismoDiaTienda::datos($pedido)['confirmada']);
        $pedido->update(['pago_validado_at' => now(), 'numero_remision' => 'REM-MD']);
        $pedido->documentos()->create(['tipo' => 'remision', 'ruta_archivo' => 'md/remision.pdf', 'nombre_original' => 'remision.pdf', 'activo' => true]);
        $this->assertTrue(\App\Support\ControlPedidos\AtencionMismoDiaTienda::datos($pedido->fresh())['confirmada']);
        $this->assertSame('2026-11-04T18:59:00Z', $pedido->fresh()->solicitud_preparacion_json['tienda_md_reporte']['primer_pago_at']);
        $json = $pedido->solicitud_preparacion_json;
        $json['tienda_md_reporte']['estado'] = 'tardio';
        $json['tienda_md_reporte']['oportuno'] = false;
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $this->expectException(\RuntimeException::class);
        \App\Support\ControlPedidos\AtencionMismoDiaTienda::assertSalida($pedido->fresh());
    }

    public function test_reconoce_caja_pdv_en_mayusculas_sin_crear_otro_banco(): void
    {
        $caja = $this->caja();
        $caja->update(['nombre' => 'CAJA PDV']);
        $pedido = $this->pedido();
        $antes = CatalogoBanco::count();
        $pago = $this->pagar($pedido, ['monto' => 10, 'forma_pago' => 'efectivo', 'referencia' => 'CAJA-MAYUSCULAS']);
        $this->assertSame($caja->id, (int) $pago->catalogo_banco_id);
        $this->assertSame($antes, CatalogoBanco::count());
    }

    private function caja(): CatalogoBanco
    {
        return CatalogoBanco::query()->create([
            'nombre' => ResolverBancoCajaPdv::NOMBRE,
            'activo' => true,
            'compartido' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function pagar(PedidoBma $pedido, array $datos): PedidoBmaPago
    {
        return app(RegistrarPagoPedidoBmaService::class)->handle(
            $pedido,
            $datos,
            UploadedFile::fake()->create('comprobante.pdf', 20, 'application/pdf'),
            $this->usuario->id
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pedido(array $extra = []): PedidoBma
    {
        $listaId = DB::table('catalogo_listas_descuento')->insertGetId([
            'nombre' => 'Lista pago '.uniqid(),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cliente = Cliente::query()->create([
            'numero_cliente' => (string) random_int(10000, 99999),
            'nombre' => 'Cliente de pago',
            'lista_actual_id' => $listaId,
            'vendedor_id' => $this->usuario->id,
        ]);
        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'ORI-'.uniqid(),
            'nombre' => 'Mostrador '.uniqid(),
            'requiere_logistica' => true,
            'activo' => true,
        ]);

        return PedidoBma::query()->create(array_merge([
            'folio' => 'PAG-'.uniqid(),
            'folio_remision' => 'FOL-PAG',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => $cliente->id,
            'departamento_operacion_id' => $this->usuario->departamento_id,
            'origen_id' => $origen->id,
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
            'solicitud_preparacion_json' => [
                'version' => 1,
                'modalidad_entrega' => 'tienda',
                'cobertura_tienda' => ['tipo' => 'apartado', 'confirmada' => false],
            ],
        ], $extra));
    }
}
