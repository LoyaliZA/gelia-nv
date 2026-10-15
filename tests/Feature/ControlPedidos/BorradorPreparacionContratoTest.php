<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\SolicitudTraspaso;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ida y vuelta de la captura que hoy vive fuera del borrador.
 * Guardar no crea tarea, carátula ni referencia oficial.
 */
class BorradorPreparacionContratoTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as private refreshIsolatedDatabase;
    }

    private User $usuario;

    public function refreshDatabase(): void
    {
        $this->assertSame('testing', config('app.env'));
        $this->assertSame('testing', $_ENV['APP_ENV']);
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->refreshIsolatedDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
    }

    public function test_autoguardar_conserva_intencion_municipal_sin_crear_tarea(): void
    {
        $pedido = $this->pedidoBorrador();
        $paqueteriaMunicipal = $this->paqueteria('Municipal Q2');
        $sucursalId = $pedido->sucursal_destino_id;

        $respuesta = $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', [
            'pedido_id' => $pedido->id,
            'revision_esperada' => (int) $pedido->fresh()->revision_registro,
            'base_captura' => $pedido->fresh()->base_captura,
            'origen_id' => $pedido->origen_id,
            'almacen_id' => $pedido->almacen_id,
            'comentarios_drive' => 'Dejar en mostrador',
            'prioridad_md' => false,
            'solicitud_preparacion' => [
                'version' => 1,
                'codigo_modalidad' => 'ENVIO_MUNICIPIO',
                'tipo_referencia' => 'COTIZACION',
                'folio_referencia' => 'COT-014',
                'entrega_municipal' => [
                    'destinatario_es_cliente' => false,
                    'destinatario_nombre' => 'Receptor alterno',
                    'destinatario_telefono' => '2221112233',
                    'municipio_destino' => 'San Pedro',
                    'direccion_referencia' => 'Calle 4 esquina',
                    'catalogo_paqueteria_id' => $paqueteriaMunicipal->id,
                    'modalidad_cobro' => 'POR_COBRAR',
                ],
            ],
        ]);

        $respuesta->assertOk();
        $fresco = $pedido->fresh();
        $this->assertSame('ENVIO_MUNICIPIO', $fresco->solicitud_preparacion_json['codigo_modalidad'] ?? null);
        $this->assertSame('San Pedro', $fresco->solicitud_preparacion_json['entrega_municipal']['municipio_destino'] ?? null);
        $this->assertFalse($fresco->solicitud_preparacion_json['entrega_municipal']['destinatario_es_cliente'] ?? true);
        $this->assertSame('Receptor alterno', $fresco->solicitud_preparacion_json['entrega_municipal']['destinatario_nombre'] ?? null);
        $this->assertSame('2221112233', $fresco->solicitud_preparacion_json['entrega_municipal']['destinatario_telefono'] ?? null);
        $this->assertSame('Calle 4 esquina', $fresco->solicitud_preparacion_json['entrega_municipal']['direccion_referencia'] ?? null);
        $this->assertSame($paqueteriaMunicipal->id, $fresco->solicitud_preparacion_json['entrega_municipal']['catalogo_paqueteria_id'] ?? null);
        $this->assertSame('POR_COBRAR', $fresco->solicitud_preparacion_json['entrega_municipal']['modalidad_cobro'] ?? null);
        $this->assertSame('COTIZACION', $fresco->solicitud_preparacion_json['tipo_referencia'] ?? null);
        $this->assertSame('COT-014', $fresco->solicitud_preparacion_json['folio_referencia'] ?? null);
        $this->assertFalse((bool) $fresco->prioridad_md);
        $this->assertSame($sucursalId, $fresco->sucursal_destino_id);
        $this->assertSame($pedido->almacen_id, $fresco->almacen_id);
        $this->assertSame('Dejar en mostrador', $fresco->comentarios_drive);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->where('pedido_bma_id', $pedido->id)->count());
        $this->assertSame(0, PedidoBmaCaratula::query()->count());
        $this->assertSame(0, SolicitudTraspaso::query()->count());
        $this->assertSame(0, ResguardoPdv::query()->count());
        $this->assertSame(0, $fresco->referencias()->count());
        $this->assertSame(CatalogoEstatusPedido::FASE_BORRADOR, $fresco->estatus->fase_ciclo);
    }

    public function test_guardado_parcial_no_borra_montos_direccion_ni_intencion(): void
    {
        $pedido = $this->pedidoBorrador([
            'total_mercancia' => 640,
            'costo_envio' => 85.5,
            'saldo_a_favor' => 20,
            'aplica_seguro' => false,
            'numero_cajas' => 0,
            'domicilio_entrega' => 'Av. Centro 10',
            'envio_por_cobrar' => false,
            'prioridad_md' => true,
            'comentarios_drive' => 'Nota previa',
        ]);
        $pedido->forceFill([
            'solicitud_preparacion_json' => [
                'version' => 1,
                'codigo_modalidad' => 'ENVIO_MUNICIPIO',
                'folio_referencia' => 'COT-9',
                'entrega_municipal' => ['municipio_destino' => 'Apizaco', 'destinatario_es_cliente' => true],
            ],
        ])->save();

        $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', [
            'pedido_id' => $pedido->id,
            'revision_esperada' => (int) $pedido->fresh()->revision_registro,
            'base_captura' => $pedido->fresh()->base_captura,
            'comentarios_drive' => 'Nota previa',
        ])->assertOk();

        $fresco = $pedido->fresh();
        $this->assertEquals(640, (float) $fresco->total_mercancia);
        $this->assertEquals(85.5, (float) $fresco->costo_envio);
        $this->assertEquals(20, (float) $fresco->saldo_a_favor);
        $this->assertFalse((bool) $fresco->aplica_seguro);
        $this->assertSame(0, (int) $fresco->numero_cajas);
        $this->assertSame('Av. Centro 10', $fresco->domicilio_entrega);
        $this->assertFalse((bool) $fresco->envio_por_cobrar);
        $this->assertTrue((bool) $fresco->prioridad_md);
        $this->assertSame('Apizaco', $fresco->solicitud_preparacion_json['entrega_municipal']['municipio_destino'] ?? null);
        $this->assertSame($pedido->almacen_id, $fresco->almacen_id);
        $this->assertSame($pedido->sucursal_destino_id, $fresco->sucursal_destino_id);
    }

    public function test_null_limpia_solo_el_campo_presente_y_cero_falso_se_conservan(): void
    {
        $pedido = $this->pedidoBorrador([
            'numero_cajas' => 0,
            'aplica_seguro' => false,
            'costo_envio' => 10,
        ]);
        $pedido->forceFill([
            'prioridad_md' => true,
            'solicitud_preparacion_json' => [
                'version' => 1,
                'codigo_modalidad' => 'ENVIO_MUNICIPIO',
                'folio_referencia' => 'COT-1',
                'entrega_municipal' => [
                    'destinatario_es_cliente' => false,
                    'municipio_destino' => 'Huamantla',
                ],
            ],
        ])->save();

        $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', [
            'pedido_id' => $pedido->id,
            'revision_esperada' => (int) $pedido->fresh()->revision_registro,
            'base_captura' => $pedido->fresh()->base_captura,
            'numero_cajas' => 0,
            'aplica_seguro' => false,
            'prioridad_md' => false,
            'solicitud_preparacion' => [
                'folio_referencia' => null,
                'entrega_municipal' => ['destinatario_es_cliente' => false],
            ],
        ])->assertOk();

        $fresco = $pedido->fresh();
        $this->assertSame(0, (int) $fresco->numero_cajas);
        $this->assertFalse((bool) $fresco->aplica_seguro);
        $this->assertEquals(10, (float) $fresco->costo_envio);
        $this->assertFalse((bool) $fresco->prioridad_md);
        $this->assertSame('ENVIO_MUNICIPIO', $fresco->solicitud_preparacion_json['codigo_modalidad'] ?? null);
        $this->assertNull($fresco->solicitud_preparacion_json['folio_referencia'] ?? null);
        $this->assertSame('Huamantla', $fresco->solicitud_preparacion_json['entrega_municipal']['municipio_destino'] ?? null);
        $this->assertFalse($fresco->solicitud_preparacion_json['entrega_municipal']['destinatario_es_cliente']);
    }

    public function test_historico_sin_intencion_no_inventa_datos_municipales(): void
    {
        $pedido = $this->pedidoBorrador();

        $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', [
            'pedido_id' => $pedido->id,
            'revision_esperada' => (int) $pedido->fresh()->revision_registro,
            'base_captura' => $pedido->fresh()->base_captura,
            'comentarios_drive' => 'Sin preparación',
        ])->assertOk();

        $fresco = $pedido->fresh();
        $this->assertNull($fresco->solicitud_preparacion_json);
        $this->assertSame('Sin preparación', $fresco->comentarios_drive);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
    }

    public function test_autoguardar_no_altera_snapshot_de_tarea_existente(): void
    {
        $pedido = $this->pedidoBorrador();
        $modalidadId = \App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido::query()->firstOrCreate(
            ['codigo' => 'ENVIO_MUNICIPIO'],
            ['nombre' => 'Municipio', 'activo' => true]
        )->id;
        $tarea = PedidoBmaTareaPreparacion::query()->create([
            'pedido_bma_id' => $pedido->id,
            'catalogo_modalidad_preparacion_id' => $modalidadId,
            'almacen_id' => $pedido->almacen_id,
            'estado' => 'PENDIENTE',
            'solicitada_por_id' => $this->usuario->id,
            'destinatario_nombre' => 'Nombre de la tarea',
            'destinatario_telefono' => '5550000000',
            'municipio_destino' => 'Municipio de la tarea',
            'direccion_referencia' => 'Referencia de la tarea',
            'modalidad_cobro' => 'PAGADO',
            'destinatario_es_cliente' => true,
        ]);
        $antes = $tarea->fresh()->only([
            'destinatario_nombre', 'destinatario_telefono', 'municipio_destino',
            'direccion_referencia', 'modalidad_cobro', 'estado', 'almacen_id',
        ]);

        $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', [
            'pedido_id' => $pedido->id,
            'revision_esperada' => (int) $pedido->fresh()->revision_registro,
            'base_captura' => $pedido->fresh()->base_captura,
            'solicitud_preparacion' => [
                'codigo_modalidad' => 'ENVIO_MUNICIPIO',
                'entrega_municipal' => [
                    'destinatario_es_cliente' => false,
                    'destinatario_nombre' => 'Otro nombre',
                    'municipio_destino' => 'Otro municipio',
                ],
            ],
        ])->assertOk();

        $this->assertSame(1, PedidoBmaTareaPreparacion::query()->count());
        $this->assertSame($antes, $tarea->fresh()->only(array_keys($antes)));
        $this->assertSame(0, PedidoBmaCaratula::query()->count());
    }

    public function test_documento_persistido_sigue_disponible_tras_guardar_intencion(): void
    {
        $pedido = $this->pedidoBorrador();
        $doc = PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO,
            'ruta_archivo' => 'pedidos_bma/q2.pdf',
            'nombre_original' => 'q2.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 12,
            'orden' => 0,
        ]);

        $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', [
            'pedido_id' => $pedido->id,
            'revision_esperada' => (int) $pedido->fresh()->revision_registro,
            'base_captura' => $pedido->fresh()->base_captura,
            'solicitud_preparacion' => [
                'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL',
            ],
        ])->assertOk();

        $this->assertTrue($pedido->documentos()->whereKey($doc->id)->exists());
        $json = json_encode($pedido->fresh()->solicitud_preparacion_json);
        $this->assertStringNotContainsString('q2.pdf', (string) $json);
    }

    public function test_sin_columna_no_confirma_el_guardado_de_la_intencion(): void
    {
        $pedido = $this->pedidoBorrador(['comentarios_drive' => 'Nota previa', 'costo_envio' => 40]);
        Schema::table('pedidos_bma', function ($table) {
            $table->dropColumn('solicitud_preparacion_json');
        });

        $respuesta = $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', [
            'pedido_id' => $pedido->id,
            'revision_esperada' => (int) $pedido->fresh()->revision_registro,
            'base_captura' => $pedido->fresh()->base_captura,
            'comentarios_drive' => 'Nota que no debe confirmarse',
            'solicitud_preparacion' => [
                'version' => 1,
                'codigo_modalidad' => 'ENVIO_MUNICIPIO',
                'entrega_municipal' => [
                    'municipio_destino' => 'San Pedro',
                ],
            ],
        ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonMissingPath('folio');
        $respuesta->assertJsonPath('message', 'La intención de preparación no se guardó: el esquema aún no tiene la columna. El guardado no quedó confirmado.');
        $fresco = $pedido->fresh();
        $this->assertSame('Nota previa', $fresco->comentarios_drive);
        $this->assertEquals(40, (float) $fresco->costo_envio);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->where('pedido_bma_id', $pedido->id)->count());
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pedidoBorrador(array $extra = []): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR],
            ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
        );
        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'Q2-'.uniqid(),
            'nombre' => 'CEDIS Q2',
            'requiere_logistica' => true,
            'activo' => true,
        ]);
        $almacen = Almacen::query()->create([
            'codigo' => 'Q2-'.uniqid(),
            'nombre' => 'CEDIS salida',
            'activo' => true,
            'visible_en_pedidos' => true,
            'permite_salida_pedidos' => true,
        ]);
        $sucursal = Sucursal::factory()->create(['activo' => true]);

        return PedidoBma::query()->create(array_merge([
            'folio' => 'Q2-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'origen_id' => $origen->id,
            'almacen_id' => $almacen->id,
            'sucursal_destino_id' => $sucursal->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'costo_envio' => 40,
            'saldo_a_favor' => 0,
            'costo_seguro' => 0,
            'total_a_cobrar' => 140,
            'aplica_seguro' => false,
            'es_resguardo' => false,
        ], $extra));
    }

    private function paqueteria(string $nombre): CatalogoPaqueteriaPedido
    {
        return CatalogoPaqueteriaPedido::query()->create([
            'nombre' => $nombre,
            'categoria' => 'local_regional',
            'activo' => true,
        ]);
    }
}
