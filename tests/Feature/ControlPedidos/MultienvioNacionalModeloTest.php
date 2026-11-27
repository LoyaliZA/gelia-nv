<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoTipoAlmacen;
use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\Nacional\CambiarNacionalATienda;
use App\Services\ControlPedidos\Nacional\CustodiaComplementoNacional;
use App\Services\ControlPedidos\Nacional\RegistrarComplementoNacional;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use App\Models\ControlPedidos\MultienvioNacional;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MultienvioNacionalModeloTest extends TestCase
{
    use RefreshDatabase;

    private User $ventas;

    private User $cedis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        $this->mock(\App\Services\ControlPedidos\NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->ventas = User::factory()->create();
        $this->ventas->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $this->cedis = User::factory()->create();
        $this->cedis->givePermissionTo([
            Permission::findOrCreate('control_pedidos.cedis', 'web'),
            Permission::findOrCreate('control_pedidos.cedis.ver_todos', 'web'),
        ]);
        $orden = 1;
        foreach ([
            CatalogoEstatusPedido::FASE_BORRADOR => 'Borrador',
            CatalogoEstatusPedido::FASE_EN_CEDIS => 'En CEDIS',
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA => 'Pendiente de guía',
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO => 'Pendiente de envío',
            CatalogoEstatusPedido::FASE_ENVIADO => 'Enviado',
        ] as $fase => $nombre) {
            CatalogoEstatusPedido::query()->firstOrCreate(
                ['fase_ciclo' => $fase],
                ['codigo_interno' => 'N3C-'.$orden, 'nombre_visual' => $nombre, 'color_hex' => '#999999', 'orden' => $orden, 'activo' => true]
            );
            $orden++;
        }
    }

    public function test_la_tabla_y_las_columnas_existen_y_son_nulas_en_pedidos_historicos(): void
    {
        $this->assertTrue(Schema::hasColumns('multienvios_nacionales', [
            'uuid', 'pedido_origen_id', 'revision_consulta', 'estado', 'snapshot_origen_json', 'borrador_json', 'regularizacion_json',
        ]));
        $pedido = $this->pedidoNacional()->fresh();
        $this->assertNull($pedido->multienvio_nacional_id);
        $this->assertNull($pedido->multienvio_orden);
        $this->assertNull($pedido->multienvio);
    }

    public function test_pedido_origen_id_es_unico(): void
    {
        $pedido = $this->pedidoNacional();
        $this->grupo($pedido);
        $this->expectException(QueryException::class);
        $this->grupo($pedido);
    }

    public function test_orden_es_unico_por_grupo(): void
    {
        $a = $this->pedidoNacional();
        $b = $this->pedidoNacional();
        $grupo = $this->grupo($a);
        $a->update(['multienvio_nacional_id' => $grupo->id, 'multienvio_orden' => 1]);
        $this->assertSame($a->id, $grupo->origen->id);
        $this->assertSame([$a->id], $grupo->envios()->pluck('id')->all());
        $this->assertSame($grupo->id, $a->fresh()->multienvio->id);
        $this->expectException(QueryException::class);
        $b->update(['multienvio_nacional_id' => $grupo->id, 'multienvio_orden' => 1]);
    }

    public function test_dos_grupos_pueden_tener_orden_uno_y_los_historicos_nulos_coexisten(): void
    {
        $a = $this->pedidoNacional();
        $b = $this->pedidoNacional();
        $this->pedidoNacional();
        $this->pedidoNacional();
        $ga = $this->grupo($a);
        $gb = $this->grupo($b);
        $a->update(['multienvio_nacional_id' => $ga->id, 'multienvio_orden' => 1]);
        $b->update(['multienvio_nacional_id' => $gb->id, 'multienvio_orden' => 1]);
        $this->assertSame(2, PedidoBma::query()->whereNull('multienvio_nacional_id')->whereNull('multienvio_orden')->count());
    }

    public function test_down_de_pedidos_bma_se_niega_con_asignados_y_funciona_sin_ellos(): void
    {
        // SQLite reconstruye la tabla al quitar columnas; se difiere la verificación de FK (la transacción de la prueba se revierte).
        \Illuminate\Support\Facades\DB::statement('PRAGMA defer_foreign_keys = ON');
        $migracion = require database_path('migrations/2026_11_27_100100_agregar_multienvio_a_pedidos_bma.php');
        $a = $this->pedidoNacional();
        $grupo = $this->grupo($a);
        $a->update(['multienvio_nacional_id' => $grupo->id, 'multienvio_orden' => 1]);

        try {
            $migracion->down();
            $this->fail('Debió lanzar excepción.');
        } catch (\RuntimeException) {
            $this->assertTrue(Schema::hasColumns('pedidos_bma', ['multienvio_nacional_id', 'multienvio_orden']));
        }

        $a->update(['multienvio_nacional_id' => null, 'multienvio_orden' => null]);
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('pedidos_bma', 'multienvio_nacional_id'));
        $this->assertFalse(Schema::hasColumn('pedidos_bma', 'multienvio_orden'));
    }

    public function test_down_de_la_tabla_se_niega_con_asignados_y_funciona_sin_ellos(): void
    {
        \Illuminate\Support\Facades\DB::statement('PRAGMA defer_foreign_keys = ON');
        $migracion = require database_path('migrations/2026_11_27_100000_create_multienvios_nacionales_table.php');
        $a = $this->pedidoNacional();
        $grupo = $this->grupo($a);
        $a->update(['multienvio_nacional_id' => $grupo->id, 'multienvio_orden' => 1]);

        try {
            $migracion->down();
            $this->fail('Debió lanzar excepción.');
        } catch (\RuntimeException) {
            $this->assertTrue(Schema::hasTable('multienvios_nacionales'));
        }

        $a->update(['multienvio_nacional_id' => null, 'multienvio_orden' => null]);
        require_once database_path('migrations/2026_11_27_100100_agregar_multienvio_a_pedidos_bma.php');
        (require database_path('migrations/2026_11_27_100100_agregar_multienvio_a_pedidos_bma.php'))->down();
        $migracion->down();
        $this->assertFalse(Schema::hasTable('multienvios_nacionales'));
    }

    public function test_casts_y_excepciones(): void
    {
        $grupo = $this->grupo($this->pedidoNacional());
        $grupo->update(['borrador_json' => ['a' => 1]]);
        $this->assertSame(['a' => 1], $grupo->fresh()->borrador_json);
        $this->assertInstanceOf(\RuntimeException::class, \App\Services\ControlPedidos\Nacional\Multienvio\MultienvioNacionalException::hechosPrevios());
        $this->assertStringContainsString('X', \App\Services\ControlPedidos\Nacional\Multienvio\MultienvioNacionalException::folioRepetido('X')->getMessage());
    }

    private function grupo(PedidoBma $origen): MultienvioNacional
    {
        return MultienvioNacional::query()->create([
            'uuid' => (string) Str::uuid(),
            'pedido_origen_id' => $origen->id,
            'revision_consulta' => 'abc',
            'estado' => MultienvioNacional::ESTADO_PENDIENTE_DECISION,
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function pedidoNacional(array $extra = []): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_EN_CEDIS)->firstOrFail();
        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'NAC-'.uniqid(),
            'nombre' => 'Nacional '.uniqid(),
            'requiere_logistica' => true,
            'activo' => true,
        ]);
        $almacen = Almacen::query()->create([
            'codigo' => 'CEDIS-'.uniqid(),
            'nombre' => 'CEDIS',
            'activo' => true,
            'permite_salida_pedidos' => true,
        ]);

        return PedidoBma::query()->create(array_merge([
            'folio' => 'NAC-'.uniqid(),
            'folio_remision' => 'WIZ-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->ventas->id,
            'origen_id' => $origen->id,
            'almacen_id' => $almacen->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'catalogo_paqueteria_id' => CatalogoPaqueteriaPedido::query()->firstOrCreate(
                ['nombre' => 'DHL Cierre '.uniqid()],
                ['categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL, 'requiere_peso' => true, 'activo' => true, 'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA]
            )->id,
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
}
