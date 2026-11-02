<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoTipoAlmacen;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\Departamento;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SolicitudRegistrarTiendaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Departamento $departamento;

    private Almacen $pdv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        $this->departamento = Departamento::query()->create(['nombre' => 'Ventas', 'activo' => true]);
        $this->usuario = User::factory()->create(['departamento_id' => $this->departamento->id]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ([
            'control_pedidos.crear',
            'control_pedidos.tienda.ver',
            'control_pedidos.cedis',
            'control_pedidos.cedis.ver_todos',
        ] as $permiso) {
            $this->usuario->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }
        foreach ([
            CatalogoEstatusPedido::FASE_BORRADOR => 'BORRADOR',
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE => 'PESAJE',
        ] as $fase => $codigo) {
            CatalogoEstatusPedido::query()->firstOrCreate(
                ['fase_ciclo' => $fase],
                ['codigo_interno' => $codigo, 'nombre_visual' => $fase, 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
            );
        }
        CatalogoModalidadPreparacionPedido::query()->firstOrCreate(
            ['codigo' => CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA],
            ['nombre' => 'Recoge en tienda', 'activo' => true]
        );
        $tipo = CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'PDV']);
        $this->pdv = Almacen::query()->create([
            'codigo' => 'SUC-211',
            'nombre' => 'Sucursal Centro',
            'activo' => true,
            'visible_en_pedidos' => true,
            'permite_salida_pedidos' => true,
            'tipo_almacen_id' => $tipo->id,
        ]);
    }

    public function test_el_borrador_guarda_el_destino_y_no_crea_tarea(): void
    {
        $pedido = $this->pedido();

        $respuesta = $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', $this->captura($pedido, [
            'solicitud_preparacion' => [
                'modalidad_entrega' => 'tienda',
                'area_revision' => 'preparacion_tienda',
                'almacen_revision_id' => $this->pdv->id,
            ],
        ]))->assertOk();

        $fresco = $pedido->fresh();
        $this->assertSame('preparacion_tienda', $fresco->solicitud_preparacion_json['area_revision']);
        $this->assertSame($this->pdv->id, (int) $fresco->solicitud_preparacion_json['almacen_revision_id']);
        $this->assertSame(CatalogoEstatusPedido::FASE_BORRADOR, $fresco->estatus->fase_ciclo);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
        $this->assertSame('preparacion_tienda', $respuesta->json('solicitud_preparacion.area_revision'));

        $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', $this->captura($fresco, [
            'solicitud_preparacion' => [
                'modalidad_entrega' => 'tienda',
                'area_revision' => 'preparacion_tienda',
                'almacen_revision_id' => $this->pdv->id,
            ],
        ]))->assertOk();
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
    }

    public function test_la_solicitud_llega_a_preparacion_tienda_sin_apartar(): void
    {
        $pedido = $this->pedido();
        $this->documento($pedido);

        $cuerpo = $this->solicitud($pedido, 'preparacion_tienda', $this->pdv->id);
        $primero = $this->actingAs($this->usuario)->postJson(
            "/control-pedidos/{$pedido->id}/solicitud-tienda",
            $cuerpo
        )->assertOk();

        $fresco = $pedido->fresh(['estatus']);
        $this->assertFalse((bool) $fresco->prioridad_md);
        $this->assertNull($fresco->pago_validado_at);
        $this->assertSame(CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE, $fresco->estatus->fase_ciclo);
        $this->assertSame('preparacion_tienda', $fresco->solicitud_preparacion_json['area_revision']);
        $this->assertArrayNotHasKey('codigo_modalidad', $fresco->solicitud_preparacion_json);
        $this->assertSame(1, PedidoBmaTareaPreparacion::query()->count());
        $tarea = PedidoBmaTareaPreparacion::query()->first();
        $this->assertSame(PedidoBmaTareaPreparacion::ESTADO_PENDIENTE, $tarea->estado);
        $this->assertSame('TIENDA', $tarea->area_responsable_codigo);
        $this->assertSame($this->pdv->id, (int) $tarea->almacen_id);
        $this->assertNull($tarea->fecha_limite);
        $this->assertSame(0, $tarea->productos()->count());
        $this->assertSame(
            PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR,
            $tarea->cumplimientoFisico->estado
        );
        $this->assertStringContainsString('Preparación Tienda', (string) $primero->json('espera'));

        $lista = $this->actingAs($this->usuario)->getJson('/control-pedidos/tienda/listado?tab=PENDIENTES')->assertOk();
        $lista->assertJsonPath('tareas.data.0.pedido.folio_remision', 'FOL-211');
        $lista->assertJsonPath('tareas.data.0.pedido.prioridad_md', false);
        $lista->assertJsonPath('tareas.data.0.almacen.nombre', 'Sucursal Centro');

        $this->actingAs($this->usuario)->getJson('/control-pedidos/tienda/tareas/'.$tarea->id)
            ->assertOk()
            ->assertJsonPath('documentos_solicitud.0.nombre_original', 'pedido-ficticio.pdf')
            ->assertJsonPath('tarea.pedido.folio_remision', 'FOL-211');

        $this->actingAs($this->usuario)->postJson(
            "/control-pedidos/{$pedido->id}/solicitud-tienda",
            $cuerpo
        )->assertOk()->assertJsonPath('idempotente', true);
        $this->assertSame(1, PedidoBmaTareaPreparacion::query()->count());
    }

    public function test_la_solicitud_llega_a_control_pedidos_sin_confirmar_disponibilidad(): void
    {
        $pedido = $this->pedido();
        $this->documento($pedido);

        $respuesta = $this->actingAs($this->usuario)->postJson(
            "/control-pedidos/{$pedido->id}/solicitud-tienda",
            $this->solicitud($pedido, 'control_pedidos')
        )->assertOk();

        $fresco = $pedido->fresh(['estatus']);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->count());
        $this->assertSame('pendiente_pesaje', $fresco->estatus_envio);
        $this->assertNull($fresco->pago_validado_at);
        $this->assertFalse((bool) $fresco->prioridad_md);
        $this->assertTrue($fresco->consultaCedisModoRevisionMercancia());
        $this->assertSame('control_pedidos', $fresco->solicitud_preparacion_json['area_revision']);
        $this->assertStringContainsString('Control Pedidos', (string) $respuesta->json('espera'));

        $this->actingAs($this->usuario)->getJson('/control-pedidos/cedis/listado?tab=PENDIENTES_PESAJE')
            ->assertOk()
            ->assertJsonPath('pedidos.data.0.folio_remision', 'FOL-211')
            ->assertJsonPath('pedidos.data.0.documentos.0.nombre_original', 'pedido-ficticio.pdf');

        $this->actingAs($this->usuario)->postJson('/control-pedidos/autoguardar', $this->captura($fresco, [
            'solicitud_preparacion' => [
                'modalidad_entrega' => 'tienda',
                'area_revision' => 'preparacion_tienda',
            ],
        ]))->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pedido(array $extra = []): PedidoBma
    {
        $listaId = DB::table('catalogo_listas_descuento')->insertGetId([
            'nombre' => 'Lista 211 '.uniqid(),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cliente = Cliente::query()->create([
            'numero_cliente' => (string) random_int(10000, 99999),
            'nombre' => 'Cliente ficticio',
            'lista_actual_id' => $listaId,
            'vendedor_id' => $this->usuario->id,
        ]);
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
            'folio_remision' => 'FOL-211',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => $cliente->id,
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

    private function documento(PedidoBma $pedido): void
    {
        PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO,
            'ruta_archivo' => 'pedidos_bma/pedido-ficticio.pdf',
            'nombre_original' => 'pedido-ficticio.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 1200,
            'orden' => 1,
            'activo' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function captura(PedidoBma $pedido, array $extra = []): array
    {
        $pedido = $pedido->fresh();

        return array_merge([
            'pedido_id' => $pedido->id,
            'revision_esperada' => (int) $pedido->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($pedido),
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function solicitud(PedidoBma $pedido, string $area, ?int $almacenId = null): array
    {
        $pedido = $pedido->fresh();

        return [
            'area_revision' => $area,
            'almacen_id' => $almacenId,
            'revision_esperada' => (int) $pedido->revision_registro,
            'base_captura' => RevisionCapturaPedido::base($pedido),
        ];
    }
}
