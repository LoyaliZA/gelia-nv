<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\ControlPedidos\AuditoriaPedidoBma;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\EliminarPedidoBmaService;
use App\Services\ControlPedidos\EliminarRegistroPedidoBmaService;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use App\Services\ControlPedidos\RestaurarRegistroPedidoBmaService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EliminarRegistroPedidoBmaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $vendedora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'control_pedidos.ver_listado',
            'control_pedidos.eliminar',
            'control_pedidos.eliminar_registro',
            'control_pedidos.eliminados',
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->admin = User::factory()->create(['name' => 'Admin Pedidos']);
        $this->admin->givePermissionTo([
            'control_pedidos.ver_listado',
            'control_pedidos.eliminar_registro',
            'control_pedidos.eliminados',
        ]);

        $this->vendedora = User::factory()->create(['name' => 'Vendedora']);
        $this->vendedora->givePermissionTo(['control_pedidos.ver_listado', 'control_pedidos.eliminar']);
    }

    public function test_admin_elimina_pedido_enviado_con_auditoria_y_papelera(): void
    {
        $pedido = $this->crearPedido(['fase' => CatalogoEstatusPedido::FASE_ENVIADO]);

        app(EliminarRegistroPedidoBmaService::class)->ejecutar(
            $pedido,
            $this->admin->id,
            'Registro duplicado por error operativo'
        );

        $pedido->refresh();
        $this->assertTrue($pedido->trashed());
        $this->assertNotNull($pedido->eliminacion_registro_at);
        $this->assertSame($this->admin->id, $pedido->eliminacion_registro_por_id);

        $this->assertDatabaseHas('auditorias_pedidos_bma', [
            'pedido_bma_id' => $pedido->id,
            'usuario_id' => $this->admin->id,
            'accion' => AuditoriaPedidoBma::ACCION_ELIMINACION,
        ]);

        $listado = app(ListarPedidosBmaService::class);
        $normales = $listado->ejecutar($this->admin, ['tab' => 'ENVIADOS'], false);
        $this->assertFalse($normales->contains('id', $pedido->id));

        $papelera = $listado->ejecutar($this->admin, ['tab' => 'ELIMINADAS'], false);
        $this->assertTrue($papelera->contains('id', $pedido->id));
    }

    public function test_restaurar_desde_papelera_limpia_marca_y_audita(): void
    {
        $pedido = $this->crearPedido(['fase' => CatalogoEstatusPedido::FASE_ENVIADO]);

        app(EliminarRegistroPedidoBmaService::class)->ejecutar(
            $pedido,
            $this->admin->id,
            'Eliminación temporal para prueba'
        );

        app(RestaurarRegistroPedidoBmaService::class)->ejecutar($pedido->id, $this->admin->id);

        $pedido->refresh();
        $this->assertFalse($pedido->trashed());
        $this->assertNull($pedido->eliminacion_registro_at);
        $this->assertNull($pedido->eliminacion_registro_por_id);

        $this->assertDatabaseHas('auditorias_pedidos_bma', [
            'pedido_bma_id' => $pedido->id,
            'accion' => AuditoriaPedidoBma::ACCION_RESTAURACION,
        ]);

        $normales = app(ListarPedidosBmaService::class)->ejecutar(
            $this->admin,
            ['tab' => 'ENVIADOS'],
            false
        );
        $this->assertTrue($normales->contains('id', $pedido->id));
    }

    public function test_borrador_vendedora_no_aparece_en_papelera_ni_auditoria_admin(): void
    {
        $pedido = $this->crearPedido([
            'fase' => CatalogoEstatusPedido::FASE_BORRADOR,
            'vendedor_id' => $this->vendedora->id,
        ]);

        $this->actingAs($this->vendedora);
        app(EliminarPedidoBmaService::class)->ejecutar($pedido);

        $pedido->refresh();
        $this->assertTrue($pedido->trashed());
        $this->assertNull($pedido->eliminacion_registro_at);

        $this->assertDatabaseMissing('auditorias_pedidos_bma', [
            'pedido_bma_id' => $pedido->id,
        ]);

        $papelera = app(ListarPedidosBmaService::class)->ejecutar(
            $this->admin,
            ['tab' => 'ELIMINADAS'],
            false
        );
        $this->assertFalse($papelera->contains('id', $pedido->id));
    }

    public function test_ruta_eliminar_registro_requiere_permiso(): void
    {
        $pedido = $this->crearPedido(['fase' => CatalogoEstatusPedido::FASE_ENVIADO]);
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('control_pedidos.ver_listado');

        $this->actingAs($sinPermiso)
            ->delete(route('control_pedidos.eliminar_registro', $pedido), [
                'motivo' => 'Intento sin permiso administrativo',
            ])
            ->assertForbidden();
    }

    public function test_destroy_borrador_no_usa_flujo_admin(): void
    {
        $pedido = $this->crearPedido([
            'fase' => CatalogoEstatusPedido::FASE_BORRADOR,
            'vendedor_id' => $this->vendedora->id,
        ]);

        $this->actingAs($this->vendedora)
            ->delete(route('control_pedidos.destroy', $pedido))
            ->assertRedirect();

        $pedido->refresh();
        $this->assertTrue($pedido->trashed());
        $this->assertNull($pedido->eliminacion_registro_at);
    }

    public function test_destroy_falla_en_fase_enviado_aunque_tenga_eliminar_registro(): void
    {
        $this->vendedora->givePermissionTo('control_pedidos.eliminar_registro');
        $pedido = $this->crearPedido([
            'fase' => CatalogoEstatusPedido::FASE_ENVIADO,
            'vendedor_id' => $this->vendedora->id,
        ]);

        $this->actingAs($this->vendedora)
            ->delete(route('control_pedidos.destroy', $pedido))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFalse($pedido->fresh()->trashed());
    }

    private function crearPedido(array $overrides = []): PedidoBma
    {
        $this->seedCatalogosMinimos();

        $fase = $overrides['fase'] ?? CatalogoEstatusPedido::FASE_BORRADOR;
        unset($overrides['fase']);

        $estatus = CatalogoEstatusPedido::porFase($fase)
            ?? CatalogoEstatusPedido::create([
                'codigo_interno' => strtoupper($fase),
                'nombre_visual' => $fase,
                'color_hex' => '#64748B',
                'fase_ciclo' => $fase,
                'orden' => 99,
                'activo' => true,
            ]);

        $vendedorId = $overrides['vendedor_id'] ?? $this->admin->id;

        return PedidoBma::create(array_merge([
            'folio' => 'PED-ADM-'.uniqid(),
            'folio_remision' => 'REM-ADM-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $vendedorId,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => CatalogoOrigenPedido::firstOrCreate(
                ['nombre' => 'Mostrador'],
                ['requiere_logistica' => false, 'activo' => true]
            )->id,
            'almacen_id' => DB::table('almacenes')->value('id'),
            'catalogo_banco_id' => DB::table('catalogo_bancos')->value('id'),
            'catalogo_tipo_caja_id' => DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'numero_cajas' => 1,
            'catalogo_paqueteria_id' => DB::table('catalogo_paqueterias_pedido')->value('id'),
            'catalogo_tipo_guia_id' => DB::table('catalogo_tipos_guia_pedido')->value('id'),
            'catalogo_zona_id' => DB::table('catalogo_zonas_pedido')->value('id'),
            'catalogo_envio_tienda_id' => DB::table('catalogo_envios_tienda')->value('id'),
            'codigo_postal' => '86000',
            'domicilio_entrega' => 'Calle Test 123',
            'total_mercancia' => 500,
            'costo_envio' => 50,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'es_resguardo' => false,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_COMPLETO,
        ], $overrides));
    }

    private function seedCatalogosMinimos(): void
    {
        $now = now();

        if (! CatalogoEstatusPedido::exists()) {
            foreach ([
                ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#94A3B8', 'fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR, 'orden' => 1],
                ['codigo_interno' => 'ENVIADO', 'nombre_visual' => 'Enviado', 'color_hex' => '#22C55E', 'fase_ciclo' => CatalogoEstatusPedido::FASE_ENVIADO, 'orden' => 9],
            ] as $row) {
                CatalogoEstatusPedido::create(array_merge($row, ['activo' => true]));
            }
        }

        if (! DB::table('catalogo_bancos')->exists()) {
            DB::table('catalogo_bancos')->insert(['nombre' => 'BBVA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (! DB::table('catalogo_listas_descuento')->exists()) {
            DB::table('catalogo_listas_descuento')->insert(['nombre' => 'Lista Test', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (! DB::table('catalogo_paqueterias_pedido')->exists()) {
            DB::table('catalogo_paqueterias_pedido')->insert(['nombre' => 'FEDEX', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (! DB::table('catalogo_tipos_caja_pedido')->exists()) {
            DB::table('catalogo_tipos_caja_pedido')->insert(['nombre' => 'CAJA TEST', 'peso_volumetrico' => 1, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (! DB::table('catalogo_tipos_guia_pedido')->exists()) {
            DB::table('catalogo_tipos_guia_pedido')->insert(['nombre' => 'Terrestre', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (! DB::table('catalogo_zonas_pedido')->exists()) {
            DB::table('catalogo_zonas_pedido')->insert(['nombre' => 'Sin reexpedición', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (! DB::table('clientes')->exists()) {
            DB::table('clientes')->insert([
                'numero_cliente' => '1001',
                'nombre' => 'Cliente Test',
                'lista_actual_id' => DB::table('catalogo_listas_descuento')->value('id'),
                'vendedor_id' => $this->admin->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if (! DB::table('almacenes')->exists()) {
            DB::table('almacenes')->insert(['codigo' => 'VTA', 'nombre' => 'CEDIS', 'created_at' => $now, 'updated_at' => $now]);
        }
        if (! DB::table('catalogo_envios_tienda')->exists()) {
            DB::table('catalogo_envios_tienda')->insert(['nombre' => 'Tienda', 'es_otro' => false, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
}
