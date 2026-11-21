<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoTipoAlmacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Notifications\AlertaPedidoBma;
use Illuminate\Support\Facades\Notification;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\ListarPedidosDelegadoService;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmpaqueMunicipalCedisTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sucursal $sucursal;

    private Almacen $cedis;

    private User $delegado;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->usuario = User::factory()->create();
        $this->sucursal = Sucursal::factory()->create(['activo' => true]);
        $tipo = CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'CEDIS'], ['activo' => true]);
        $this->cedis = Almacen::query()->create([
            'nombre' => 'CEDIS-EMP', 'codigo' => 'CEDIS-EMP', 'tipo_almacen_id' => $tipo->id,
            'activo' => true, 'visible_en_pedidos' => true,
        ]);
        $this->seedCatalogos();
        Permission::findOrCreate('control_pedidos.cedis');
        $this->usuario->givePermissionTo('control_pedidos.cedis');
        Permission::findOrCreate('control_pedidos.delegado');
        $this->delegado = User::factory()->create();
        $this->delegado->givePermissionTo('control_pedidos.delegado');
    }

    private function alertasGuia(): int
    {
        return Notification::sent($this->delegado, AlertaPedidoBma::class, fn ($n) => $n->tipoAlerta === 'pedido_pendiente_guia')->count();
    }

    public function test_empaque_municipal_termina_en_pendiente_de_envio(): void
    {
        $pedido = $this->pedido('municipal');

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id, $this->bultos($pedido));

        $pedido = $pedido->fresh(['estatus']);
        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO, $pedido->estatus->fase_ciclo);
        $this->assertNull($pedido->numero_rastreo);
        $this->assertNotNull($pedido->empacado_at);
    }

    public function test_empaque_municipal_no_notifica_al_delegado(): void
    {
        Notification::fake();
        $pedido = $this->pedido('municipal');

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id, $this->bultos($pedido));

        $this->assertSame(0, $this->alertasGuia());
    }

    public function test_delegado_no_lista_municipal(): void
    {
        $pedido = $this->pedido('municipal');
        $servicio = app(ListarPedidosDelegadoService::class);

        foreach (['PENDIENTES_GUIA', 'PENDIENTES_ENVIO', 'TODOS'] as $tab) {
            $this->assertNotContains($pedido->id, $servicio->ejecutar(['tab' => $tab], false)->pluck('id')->all(), "antes del empaque, tab {$tab}");
        }

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id, $this->bultos($pedido));

        foreach (['PENDIENTES_GUIA', 'PENDIENTES_ENVIO', 'TODOS'] as $tab) {
            $this->assertNotContains($pedido->id, $servicio->ejecutar(['tab' => $tab], false)->pluck('id')->all(), "tras el empaque, tab {$tab}");
        }
    }

    public function test_empaque_municipal_exige_remision_pago_y_resguardo_liberado(): void
    {
        $sinRemision = $this->pedido('municipal', [], conRemision: false);
        $this->assertEmpaqueRechazado($sinRemision, 'pago validado y remisión');

        $sinPago = $this->pedido('municipal', ['pago_validado_at' => null, 'pago_validado_por_id' => null]);
        $this->assertEmpaqueRechazado($sinPago, 'pago validado y remisión');

        $resguardo = $this->pedido('municipal', ['es_resguardo' => true]);
        $this->assertEmpaqueRechazado($resguardo, 'resguardo');
    }

    public function test_empaque_municipal_exige_bultos_con_evidencia(): void
    {
        $pedido = $this->pedido('municipal');

        try {
            app(MarcarEmpacadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id, []);
            $this->fail('Debió exigir bultos.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('bultos', $e->getMessage());
        }
        $this->assertNull($pedido->fresh()->empacado_at);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id, $this->bultos($pedido, 2));
        $pedido = $pedido->fresh(['bultosEmpaque.documentos']);
        $this->assertCount(2, $pedido->bultosEmpaque);
        $this->assertSame(4, PedidoBmaDocumento::query()->where('pedido_bma_id', $pedido->id)
            ->whereIn('tipo', [PedidoBmaDocumento::TIPO_EVIDENCIA_BULTO_EMPAQUE, PedidoBmaDocumento::TIPO_EVIDENCIA_TICKET_BULTO_EMPAQUE])->count());
    }

    public function test_delegado_sigue_listando_nacional_y_municipal_sin_marca(): void
    {
        $nacional = $this->pedido('nacional', ['sucursal_destino_id' => null]);
        $legado = $this->pedido('municipal', ['sucursal_destino_id' => null], marca: false);

        $ids = app(ListarPedidosDelegadoService::class)->ejecutar(['tab' => 'PENDIENTES_GUIA'], false)->pluck('id')->all();

        $this->assertContains($nacional->id, $ids);
        $this->assertContains($legado->id, $ids);
    }

    public function test_delegado_lista_municipal_marcado_con_tarea_envio_municipio_abierta(): void
    {
        $pedido = $this->pedido('municipal');
        $modalidad = \App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido::query()
            ->where('codigo', \App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO)->firstOrFail();
        \App\Models\ControlPedidos\PedidoBmaTareaPreparacion::query()->create([
            'pedido_bma_id' => $pedido->id, 'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => $this->cedis->id, 'estado' => \App\Models\ControlPedidos\PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            'area_responsable_codigo' => 'TIENDA', 'version' => 1,
        ]);

        $ids = app(ListarPedidosDelegadoService::class)->ejecutar(['tab' => 'PENDIENTES_GUIA'], false)->pluck('id')->all();

        $this->assertContains($pedido->id, $ids, 'Con tarea ENVIO_MUNICIPIO abierta conserva su camino anterior por Delegado.');
    }

    public function test_nacional_sigue_pasando_por_guia(): void
    {
        Notification::fake();
        $pedido = $this->pedido('nacional', ['sucursal_destino_id' => null]);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id, []);

        $pedido = $pedido->fresh(['estatus']);
        $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA, $pedido->estatus->fase_ciclo);
        $this->assertSame(1, $this->alertasGuia());
    }

    private function assertEmpaqueRechazado(PedidoBma $pedido, string $fragmento): void
    {
        try {
            app(MarcarEmpacadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id, $this->bultos($pedido));
            $this->fail('Debió rechazar el empaque.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($fragmento, $e->getMessage());
        }
        $this->assertNull($pedido->fresh()->empacado_at);
    }

    /** @return array<int, list<array{foto_bulto: UploadedFile, foto_ticket: UploadedFile}>> */
    private function bultos(PedidoBma $pedido, int $n = 1): array
    {
        $filas = [];
        for ($i = 0; $i < $n; $i++) {
            $filas[] = ['foto_bulto' => UploadedFile::fake()->image("b{$i}.jpg"), 'foto_ticket' => UploadedFile::fake()->image("t{$i}.jpg")];
        }

        return [$pedido->id => $filas];
    }

    private function pedido(string $modalidad, array $extra = [], bool $conRemision = true, bool $marca = true): PedidoBma
    {
        $json = ['modalidad_entrega' => $modalidad];
        if ($modalidad === 'municipal' && $marca) {
            $json['recorrido_fisico_municipal'] = true;
        }
        $comercial = CatalogoPaqueteriaPedido::query()->where('categoria', CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL)->first();

        $pedido = PedidoBma::query()->create(array_merge([
            'folio' => 'PED-EMC-'.uniqid(),
            'folio_remision' => 'REM-EMC-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => CatalogoOrigenPedido::query()->where('nombre', 'Envío Foráneo')->value('id'),
            'almacen_id' => $this->cedis->id,
            'sucursal_destino_id' => $this->sucursal->id,
            'catalogo_banco_id' => DB::table('catalogo_bancos')->value('id'),
            'catalogo_tipo_caja_id' => DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'numero_cajas' => 1,
            'peso_real_kg' => 1.5,
            'catalogo_paqueteria_id' => $comercial->id,
            'catalogo_tipo_guia_id' => DB::table('catalogo_tipos_guia_pedido')->value('id'),
            'catalogo_zona_id' => DB::table('catalogo_zonas_pedido')->value('id'),
            'total_mercancia' => 1000,
            'costo_envio' => 0,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS)->id,
            'es_resguardo' => false,
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->usuario->id,
            'solicitud_preparacion_json' => $json,
        ], $extra));

        if ($conRemision) {
            PedidoBmaDocumento::query()->create([
                'pedido_bma_id' => $pedido->id, 'tipo' => PedidoBmaDocumento::TIPO_REMISION, 'ruta_archivo' => 'test/remision.pdf',
                'nombre_original' => 'remision.pdf', 'mime_type' => 'application/pdf', 'tamano_bytes' => 100, 'orden' => 0,
            ]);
        }

        return $pedido;
    }

    private function seedCatalogos(): void
    {
        $now = now();
        foreach ([
            ['BORRADOR', 'BORRADOR', 1], ['AZUL_1', 'PENDIENTE_AUXILIAR', 2], ['AMARILLO', 'EN_CEDIS', 3],
            ['PENDIENTE_GUIA', 'PENDIENTE_DE_GUIA', 7], ['PENDIENTE_ENVIO', 'PENDIENTE_DE_ENVIO', 10],
            ['ENTREGADO', 'ENTREGADO', 8], ['ENVIADO', 'ENVIADO', 9],
        ] as [$codigo, $fase, $orden]) {
            CatalogoEstatusPedido::query()->firstOrCreate(['codigo_interno' => $codigo], [
                'nombre_visual' => $codigo, 'color_hex' => '#3B82F6', 'fase_ciclo' => $fase, 'orden' => $orden, 'activo' => true,
            ]);
        }
        CatalogoOrigenPedido::query()->firstOrCreate(['nombre' => 'Envío Foráneo'], ['requiere_logistica' => true, 'activo' => true]);
        DB::table('catalogo_bancos')->insertOrIgnore(['nombre' => 'BBVA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('catalogo_listas_descuento')->insertOrIgnore(['nombre' => 'Lista Test', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('catalogo_paqueterias_pedido')->insertOrIgnore(['nombre' => 'PAQ COMERCIAL', 'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('catalogo_tipos_caja_pedido')->insertOrIgnore(['nombre' => 'CAJA TEST', 'peso_volumetrico' => 1, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('catalogo_tipos_guia_pedido')->insertOrIgnore(['nombre' => 'Terrestre', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('catalogo_zonas_pedido')->insertOrIgnore(['nombre' => 'Zona 1', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        $lista = DB::table('catalogo_listas_descuento')->value('id');
        DB::table('clientes')->insertOrIgnore([
            'numero_cliente' => 'EMC-1', 'nombre' => 'Cliente', 'lista_actual_id' => $lista, 'vendedor_id' => $this->usuario->id,
            'monto_venta_actual' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
