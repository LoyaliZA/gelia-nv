<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\User;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Services\ControlPedidos\MarcarEnviadoPedidoBmaService;
use App\Services\ControlPedidos\Nacional\RegistrarComplementoNacional;
use App\Services\ControlPedidos\SolicitarPesajePedidoBmaService;
use App\Support\ControlPedidos\Nacional\CapturaNacional;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Anexo extraordinario: minipedido nacional relacionado, con su propia caja, guía, pago y remisión. */
class NacionalAnexoExtraordinarioTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        $this->mock(\App\Services\ControlPedidos\NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $orden = 1;
        foreach ([
            CatalogoEstatusPedido::FASE_BORRADOR => 'Borrador',
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE => 'Pesaje pendiente',
            CatalogoEstatusPedido::FASE_EN_CEDIS => 'En CEDIS',
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA => 'Pendiente de guía',
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO => 'Pendiente de envío',
            CatalogoEstatusPedido::FASE_ENVIADO => 'Enviado',
            CatalogoEstatusPedido::FASE_CANCELADO => 'Cancelado',
        ] as $fase => $nombre) {
            CatalogoEstatusPedido::query()->firstOrCreate(
                ['fase_ciclo' => $fase],
                ['codigo_interno' => 'ANX-'.$orden, 'nombre_visual' => $nombre, 'color_hex' => '#999999', 'orden' => $orden, 'activo' => true]
            );
            $orden++;
        }
    }

    public function test_el_hijo_nace_independiente_sin_pago_y_con_su_folio_wizerp(): void
    {
        Storage::fake('public');
        $principal = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, [
            'numero_remision' => 'REM-1',
            'pago_validado_at' => now(),
            'cliente_direccion_id' => null,
        ]);
        $datos = $this->datos($principal, [
            ['sku' => 'SKU-A', 'descripcion' => 'Pieza A', 'cantidad' => 2],
            ['sku' => '', 'descripcion' => 'Pieza B', 'cantidad' => 1, 'evidencia' => UploadedFile::fake()->image('b.jpg')],
        ]);

        $hijo = app(RegistrarComplementoNacional::class)->ejecutar($principal, $this->usuario, $datos, [$datos['documento']]);

        $this->assertNull($hijo->pedido_principal_id);
        $this->assertNull($hijo->pago_validado_at);
        $this->assertNull($hijo->pago_validado_por_id);
        $this->assertSame('WIZ-777', $hijo->folio_remision);
        $this->assertStringEndsWith('-C1', $hijo->folio);
        $json = $hijo->solicitud_preparacion_json;
        $this->assertSame($principal->id, $json['complemento_nacional']['pedido_origen_id']);
        $this->assertSame($principal->folio, $json['complemento_nacional']['pedido_origen_folio']);
        $this->assertCount(2, $json['piezas_nacionales']);
        $this->assertCount(2, CapturaNacional::piezasVigentes($hijo));
        $this->assertSame([], $hijo->revisionesProducto()->pluck('id')->all(), 'CEDIS crea las revisiones al responder');
        $this->assertSame(array_column($json['piezas_nacionales'], 'id'), array_column($json['complemento_nacional']['piezas'], 'id'));
        foreach ($json['piezas_nacionales'] as $pieza) {
            $this->assertTrue(Str::isUuid($pieza['id']));
        }
        $this->assertNull($json['piezas_nacionales'][1]['sku']);
        $this->assertCount(1, $json['piezas_nacionales'][1]['documento_ids']);
        $this->assertSame(1, $hijo->documentos()->where('relacion_tipo', CapturaNacional::RELACION_PIEZA_SOLICITADA)->count());
        $this->assertSame(1, $hijo->documentos()->where('tipo', 'pdf_pedido')->count());
        $this->assertTrue($hijo->tieneSoporteSolicitud());

        $principal->refresh();
        $this->assertSame('REM-1', $principal->numero_remision);
        $this->assertNotNull($principal->pago_validado_at);
    }

    public function test_el_reintento_con_la_misma_solicitud_no_duplica(): void
    {
        Storage::fake('public');
        $principal = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, ['numero_remision' => 'REM-2']);
        $datos = $this->datos($principal, [['sku' => 'SKU-A', 'descripcion' => 'Pieza A', 'cantidad' => 1]]);

        $primero = app(RegistrarComplementoNacional::class)->ejecutar($principal, $this->usuario, $datos, [$datos['documento']]);
        $segundo = app(RegistrarComplementoNacional::class)->ejecutar($principal->fresh(), $this->usuario, $datos, [$datos['documento']]);

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(2, PedidoBma::query()->count());
        $this->assertCount(1, $primero->solicitud_preparacion_json['piezas_nacionales']);
        $this->assertSame(1, $primero->documentos()->count(), 'un solo documento general y nada duplicado');
    }

    public function test_pieza_sin_sku_ni_archivo_y_sin_folio_wizerp_se_rechazan(): void
    {
        Storage::fake('public');
        $principal = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, ['numero_remision' => 'REM-3']);

        $sinPieza = $this->datos($principal, [['sku' => '', 'descripcion' => 'Sin soporte', 'cantidad' => 1]]);
        $primero = $this->rechazo(fn () => app(RegistrarComplementoNacional::class)->ejecutar($principal, $this->usuario, $sinPieza, [$sinPieza['documento']]));
        $this->assertNotNull($primero);
        $this->assertStringContainsString('Adjunte la captura', $primero->getMessage());

        $sinFolio = $this->datos($principal, [['sku' => 'SKU-A', 'descripcion' => 'Pieza', 'cantidad' => 1]]);
        $sinFolio['folio_wizerp'] = '';
        $segundo = $this->rechazo(fn () => app(RegistrarComplementoNacional::class)->ejecutar($principal, $this->usuario, $sinFolio, [$sinFolio['documento']]));
        $this->assertNotNull($segundo);
        $this->assertStringContainsString('folio', mb_strtolower($segundo->getMessage()));
        $this->assertSame(1, PedidoBma::query()->count());
    }

    public function test_si_el_principal_ya_salio_el_anexo_es_envio_nuevo_y_no_toca_la_caja(): void
    {
        Storage::fake('public');
        $principal = $this->pedido(CatalogoEstatusPedido::FASE_ENVIADO, ['numero_remision' => 'REM-4', 'numero_rastreo' => 'SAL-1']);
        $caja = $this->caja($principal, [
            'numero_rastreo' => 'SAL-C',
            'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_RECOLECTADA,
        ]);
        $datos = $this->datos($principal, [['sku' => 'SKU-A', 'descripcion' => 'Pieza A', 'cantidad' => 1]]);

        $hijo = app(RegistrarComplementoNacional::class)->ejecutar($principal, $this->usuario, $datos, [$datos['documento']]);

        $this->assertFalse($hijo->solicitud_preparacion_json['complemento_nacional']['antes_de_salida']);
        $this->assertNull($hijo->pedido_principal_id);
        $caja = $caja->fresh();
        $this->assertSame(PedidoBmaCaja::ESTATUS_RECOLECTADA, $caja->estatus_recoleccion);
        $this->assertSame('SAL-C', $caja->numero_rastreo);
    }


    public function test_http_crea_el_anexo_y_deja_su_consulta_pendiente(): void
    {
        Storage::fake('public');
        $principal = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, ['numero_remision' => 'REM-H1']);

        $respuesta = $this->actingAs($this->usuario)
            ->post(route('control_pedidos.anexo_extraordinario', $principal), $this->cuerpoHttp($principal), ['Accept' => 'application/json']);

        $respuesta->assertOk()->assertJsonPath('ok', true)->assertJsonPath('anexo.estatus_fase', CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE);
        $hijo = PedidoBma::query()->findOrFail($respuesta->json('anexo.id'));
        $this->assertNull($hijo->pago_validado_at);
        $this->assertNull($hijo->pedido_principal_id);
        $this->assertSame($principal->folio, $respuesta->json('anexo.folio_principal'));
        $this->assertNotNull($hijo->pesaje_solicitado_at);
    }

    public function test_http_rechaza_principal_sin_pago_ni_remision_o_cancelado(): void
    {
        Storage::fake('public');
        $sinPago = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS);
        $this->actingAs($this->usuario)
            ->post(route('control_pedidos.anexo_extraordinario', $sinPago), $this->cuerpoHttp($sinPago), ['Accept' => 'application/json'])
            ->assertStatus(422);

        $cancelado = $this->pedido(CatalogoEstatusPedido::FASE_CANCELADO, ['numero_remision' => 'REM-H2']);
        $this->actingAs($this->usuario)
            ->post(route('control_pedidos.anexo_extraordinario', $cancelado), $this->cuerpoHttp($cancelado), ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->assertSame(2, PedidoBma::query()->count());
    }

    public function test_http_sin_permiso_responde_403(): void
    {
        Storage::fake('public');
        $principal = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, ['numero_remision' => 'REM-H3']);
        $otro = User::factory()->create();

        $this->actingAs($otro)
            ->post(route('control_pedidos.anexo_extraordinario', $principal), $this->cuerpoHttp($principal), ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_el_ajuste_ordinario_con_remision_ya_no_crea_complemento(): void
    {
        Storage::fake('public');
        $principal = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, ['numero_remision' => 'REM-H4']);
        $datos = $this->datos($principal, [['sku' => 'SKU-A', 'descripcion' => 'Pieza A', 'cantidad' => 1]]);
        $datos['tipo'] = 'anexo';
        $datos['evidencia'] = $datos['documento'];

        $this->actingAs($this->usuario)
            ->post(route('control_pedidos.ajuste_nacional', $principal), $datos, ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertSee('Anexo extraordinario');
        $this->assertSame(1, PedidoBma::query()->count());
    }

    public function test_el_anexo_pendiente_no_frena_guia_ni_salida_del_principal(): void
    {
        Storage::fake('public');
        $principal = $this->pedido(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, [
            'numero_remision' => 'REM-S1',
            'numero_rastreo' => 'GUIA-P',
            'empacado_at' => now(),
        ]);
        $this->caja($principal, ['numero_rastreo' => 'GUIA-P', 'peso_real_kg' => 1]);
        PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $principal->id, 'tipo' => PedidoBmaDocumento::TIPO_GUIA, 'ruta_archivo' => 'guias/p.pdf',
            'nombre_original' => 'p.pdf', 'mime_type' => 'application/pdf', 'activo' => true,
        ]);
        $datos = $this->datos($principal, [['sku' => 'SKU-A', 'descripcion' => 'Pieza A', 'cantidad' => 1]]);
        $hijo = app(RegistrarComplementoNacional::class)->ejecutar($principal, $this->usuario, $datos, [$datos['documento']]);
        $this->assertNull($hijo->pesaje_solicitado_at, 'el anexo sigue pendiente');

        $enviado = app(MarcarEnviadoPedidoBmaService::class)->ejecutar($principal->fresh(), $this->usuario->id);

        $this->assertSame(CatalogoEstatusPedido::FASE_ENVIADO, $enviado->estatus->fase_ciclo);
        $this->assertSame(CatalogoEstatusPedido::FASE_BORRADOR, $hijo->fresh()->estatus->fase_ciclo);
    }

    public function test_el_hijo_aparece_como_pedido_propio_en_el_listado_de_cedis(): void
    {
        Storage::fake('public');
        $principal = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, ['numero_remision' => 'REM-L1']);
        $datos = $this->datos($principal, [['sku' => 'SKU-A', 'descripcion' => 'Pieza A', 'cantidad' => 1]]);
        $hijo = app(RegistrarComplementoNacional::class)->ejecutar($principal, $this->usuario, $datos, [$datos['documento']]);
        app(SolicitarPesajePedidoBmaService::class)->ejecutar($hijo->fresh(['estatus', 'origen']), $this->usuario->id);

        $cedis = User::factory()->create();
        $cedis->givePermissionTo(Permission::findOrCreate('control_pedidos.cedis', 'web'));
        $cedis->givePermissionTo(Permission::findOrCreate('control_pedidos.cedis.ver_todos', 'web'));
        $respuesta = $this->actingAs($cedis)->getJson('/control-pedidos/cedis/listado?tab=TODOS')->assertOk();

        $ids = collect($respuesta->json('pedidos.data'))->pluck('id')->all();
        $this->assertContains($hijo->id, $ids, 'el anexo es una tarjeta propia, no se agrupa con el principal');
        $tarjeta = collect($respuesta->json('pedidos.data'))->firstWhere('id', $hijo->id);
        $this->assertSame($principal->id, $tarjeta['solicitud_preparacion']['complemento_nacional']['pedido_origen_id']);
    }

    public function test_las_claves_del_anexo_sobreviven_a_la_lectura_y_a_la_fusion(): void
    {
        Storage::fake('public');
        $principal = $this->pedido(CatalogoEstatusPedido::FASE_EN_CEDIS, ['numero_remision' => 'REM-F1']);
        $datos = $this->datos($principal, [['sku' => 'SKU-A', 'descripcion' => 'Pieza A', 'cantidad' => 1]]);
        $hijo = app(RegistrarComplementoNacional::class)->ejecutar($principal, $this->usuario, $datos, [$datos['documento']]);

        $leido = $hijo->fresh()->solicitud_preparacion;
        $this->assertSame($principal->folio, $leido['complemento_nacional']['pedido_origen_folio']);
        $this->assertCount(1, $leido['piezas_nacionales']);
        $this->assertSame($hijo->id, $principal->fresh()->solicitud_preparacion['complementos_nacionales'][0]['pedido_id']);
    }

    /** @return array<string, mixed> */
    private function cuerpoHttp(PedidoBma $principal): array
    {
        $datos = $this->datos($principal, [['sku' => 'SKU-A', 'descripcion' => 'Pieza A', 'cantidad' => 2]]);
        unset($datos['evidencia']);

        return $datos;
    }

    /** @param  list<array<string, mixed>>  $piezas */
    private function datos(PedidoBma $principal, array $piezas): array
    {
        $fresco = $principal->fresh();

        return [
            'solicitud_id' => (string) Str::uuid(),
            'folio_wizerp' => 'WIZ-777',
            'nota' => 'El cliente agregó piezas',
            'piezas' => $piezas,
            'total_mercancia_nueva' => 250,
            'revision_esperada' => (int) $fresco->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($fresco),
            'documento' => UploadedFile::fake()->create('anexo.pdf', 20, 'application/pdf'),
        ];
    }

    private function rechazo(callable $accion): ?\Throwable
    {
        try {
            $accion();
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $e;
        }

        return null;
    }

    /** @param  array<string, mixed>  $extra */
    private function pedido(string $fase, array $extra = []): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()->where('fase_ciclo', $fase)->firstOrFail();
        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'NAC-'.uniqid(), 'nombre' => 'Nacional '.uniqid(), 'requiere_logistica' => true, 'activo' => true,
        ]);
        $almacen = Almacen::query()->create([
            'codigo' => 'CEDIS-'.uniqid(), 'nombre' => 'CEDIS', 'activo' => true, 'permite_salida_pedidos' => true,
        ]);

        return PedidoBma::query()->create(array_merge([
            'folio' => 'NAC-'.uniqid(),
            'folio_remision' => 'WIZ-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'origen_id' => $origen->id,
            'almacen_id' => $almacen->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'catalogo_paqueteria_id' => CatalogoPaqueteriaPedido::query()->create([
                'nombre' => 'DHL '.uniqid(),
                'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL,
                'requiere_peso' => true,
                'activo' => true,
                'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA,
            ])->id,
            'solicitud_preparacion_json' => [
                'modalidad_entrega' => 'nacional',
                'captura_nacional' => 'compartida',
                'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL',
            ],
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'saldo_a_favor' => 0,
            'costo_seguro' => 0,
            'total_a_cobrar' => 100,
            'aplica_seguro' => false,
            'es_resguardo' => false,
        ], $extra));
    }

    /** @param  array<string, mixed>  $extra */
    private function caja(PedidoBma $pedido, array $extra = []): PedidoBmaCaja
    {
        return PedidoBmaCaja::query()->create(array_merge([
            'pedido_bma_id' => $pedido->id,
            'uuid_operativo' => (string) Str::uuid(),
            'catalogo_tipo_caja_id' => CatalogoTipoCajaPedido::query()->firstOrCreate(['nombre' => 'Caja nacional'], ['activo' => true])->id,
            'orden' => 1,
            'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
            'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_PENDIENTE,
        ], $extra));
    }
}
