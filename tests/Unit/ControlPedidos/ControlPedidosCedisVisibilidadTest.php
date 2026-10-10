<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\Departamento;
use App\Models\User;
use App\Services\ControlPedidos\ListarPedidosCedisService;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ControlPedidosCedisVisibilidadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ]);
    }

    public function test_permiso_ver_todos_existe_y_no_se_hereda_de_cedis(): void
    {
        $this->assertTrue(
            Permission::query()
                ->where('name', 'control_pedidos.cedis.ver_todos')
                ->where('guard_name', 'web')
                ->exists()
        );

        Permission::findOrCreate('control_pedidos.cedis', 'web');
        $rol = Role::findOrCreate('CedisEmpaqueVis', 'web');
        $rol->givePermissionTo('control_pedidos.cedis');

        $this->assertFalse($rol->hasPermissionTo('control_pedidos.cedis.ver_todos'));
    }

    public function test_cedis_solo_ve_pedidos_de_sus_departamentos(): void
    {
        [$aromas, $bellaroma] = $this->departamentos();
        $vendeAromas = User::factory()->create(['departamento_id' => $aromas->id]);
        $vendeBellaroma = User::factory()->create(['departamento_id' => $bellaroma->id]);
        $vendeSinDepto = User::factory()->create(['departamento_id' => null]);
        $cedisAromas = $this->usuarioCedis($aromas->id);
        $cedisAmbos = $this->usuarioCedis($aromas->id, [$bellaroma->id]);
        $cedisVacio = $this->usuarioCedis(null);
        $gerente = $this->usuarioCedis($aromas->id);
        $gerente->assignRole(Role::findOrCreate('Gerente', 'web'));
        $admin = $this->usuarioCedis($aromas->id);
        $admin->assignRole(Role::findOrCreate('Super Admin', 'web'));
        $verTodos = $this->usuarioCedis($aromas->id);
        $verTodos->givePermissionTo(
            Permission::findOrCreate('control_pedidos.cedis.ver_todos', 'web')
        );

        $pedidoAromas = $this->pedidoEnCedis($vendeAromas, 'CEDIS-AROMAS');
        $pedidoBellaroma = $this->pedidoEnCedis($vendeBellaroma, 'CEDIS-BELLA');
        $pedidoSinDepto = $this->pedidoEnCedis($vendeSinDepto, 'CEDIS-SIN');

        $this->assertTrue(VisibilidadPedidoBma::puedeVerEnCedis($cedisAromas, $pedidoAromas));
        $this->assertFalse(VisibilidadPedidoBma::puedeVerEnCedis($cedisAromas, $pedidoBellaroma));
        $this->assertFalse(VisibilidadPedidoBma::puedeVerEnCedis($cedisAromas, $pedidoSinDepto));
        $this->assertTrue(VisibilidadPedidoBma::puedeVerEnCedis($cedisAmbos, $pedidoAromas));
        $this->assertTrue(VisibilidadPedidoBma::puedeVerEnCedis($cedisAmbos, $pedidoBellaroma));
        $this->assertFalse(VisibilidadPedidoBma::puedeVerEnCedis($cedisVacio, $pedidoAromas));
        $this->assertFalse(VisibilidadPedidoBma::puedeVerEnCedis($gerente, $pedidoBellaroma));
        $this->assertTrue(VisibilidadPedidoBma::puedeVerEnCedis($admin, $pedidoBellaroma));
        $this->assertTrue(VisibilidadPedidoBma::puedeVerEnCedis($verTodos, $pedidoBellaroma));
        $this->assertTrue(VisibilidadPedidoBma::puedeVerEnCedis($verTodos, $pedidoSinDepto));

        $this->assertTrue(VisibilidadPedidoBma::puedeConsultar($cedisAromas, $pedidoAromas));
        $this->assertFalse(VisibilidadPedidoBma::puedeConsultar($cedisAromas, $pedidoBellaroma));
    }

    public function test_listado_cedis_respeta_departamento_busqueda_y_contadores(): void
    {
        [$aromas, $bellaroma] = $this->departamentos();
        $vendeAromas = User::factory()->create(['departamento_id' => $aromas->id]);
        $vendeBellaroma = User::factory()->create(['departamento_id' => $bellaroma->id]);
        $cedisAromas = $this->usuarioCedis($aromas->id);
        $cedisAmbos = $this->usuarioCedis($aromas->id, [$bellaroma->id]);
        $cedisVacio = $this->usuarioCedis(null);
        $pedidoAromas = $this->pedidoEnCedis($vendeAromas, 'LIST-AROMAS');
        $pedidoBellaroma = $this->pedidoEnCedis($vendeBellaroma, 'LIST-BELLA');

        $servicio = app(ListarPedidosCedisService::class);
        $lista = $servicio->ejecutar(['tab' => 'EMPACADOS'], false, $cedisAromas);

        $this->assertTrue($lista->contains('id', $pedidoAromas->id));
        $this->assertFalse($lista->contains('id', $pedidoBellaroma->id));

        $listaAmbos = $servicio->ejecutar(['tab' => 'EMPACADOS'], false, $cedisAmbos);
        $this->assertTrue($listaAmbos->contains('id', $pedidoAromas->id));
        $this->assertTrue($listaAmbos->contains('id', $pedidoBellaroma->id));

        $listaVacia = $servicio->ejecutar(['tab' => 'EMPACADOS'], false, $cedisVacio);
        $this->assertTrue($listaVacia->isEmpty());

        $busqueda = $servicio->ejecutar([
            'tab' => 'TODOS',
            'q' => $pedidoBellaroma->folio,
        ], false, $cedisAromas);
        $this->assertFalse($busqueda->contains('id', $pedidoBellaroma->id));

        $metricas = $servicio->metricas($cedisAromas);
        $this->assertSame(1, $metricas['empacados']);
        $this->assertSame(1, $metricas['total']);
    }

    public function test_empacar_pedido_de_otro_departamento_responde_403_sin_cambiar_estatus(): void
    {
        [$aromas, $bellaroma] = $this->departamentos();
        $vendeBellaroma = User::factory()->create(['departamento_id' => $bellaroma->id]);
        $cedisAromas = $this->usuarioCedis($aromas->id);
        $pedido = $this->pedidoEnCedis($vendeBellaroma, 'ACCION-BELLA');
        $estatusId = $pedido->catalogo_estatus_pedido_id;

        $this->actingAs($cedisAromas)
            ->post(route('control_pedidos.cedis.marcar_empacado', $pedido))
            ->assertForbidden();

        $this->assertSame($estatusId, $pedido->fresh()->catalogo_estatus_pedido_id);
    }

    public function test_liberaciones_cedis_excluyen_el_otro_departamento(): void
    {
        [$aromas, $bellaroma] = $this->departamentos();
        $vendeAromas = User::factory()->create(['departamento_id' => $aromas->id]);
        $vendeBellaroma = User::factory()->create(['departamento_id' => $bellaroma->id]);
        $cedisAromas = $this->usuarioCedis($aromas->id);
        $pedidoAromas = $this->pedidoEnCedis($vendeAromas, 'LIB-AROMAS');
        $pedidoBellaroma = $this->pedidoEnCedis($vendeBellaroma, 'LIB-BELLA');

        $almacenId = DB::table('almacenes')->insertGetId([
            'codigo' => 'VIS'.substr(uniqid(), -6),
            'nombre' => 'Almacen vis',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $modalidad = CatalogoModalidadPreparacionPedido::create([
            'codigo' => 'VIS_CEDIS_'.uniqid(),
            'nombre' => 'CEDIS vis',
            'area_responsable_codigo' => 'CEDIS',
            'activo' => true,
            'orden' => 1,
        ]);

        $tareaPropia = PedidoBmaTareaPreparacion::create([
            'pedido_bma_id' => $pedidoAromas->id,
            'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => $almacenId,
            'area_responsable_codigo' => 'CEDIS',
            'estado' => PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA,
        ]);
        $tareaAjena = PedidoBmaTareaPreparacion::create([
            'pedido_bma_id' => $pedidoBellaroma->id,
            'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => $almacenId,
            'area_responsable_codigo' => 'CEDIS',
            'estado' => PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA,
        ]);

        $this->actingAs($cedisAromas)
            ->get(route('control_pedidos.cedis.index', ['tab' => 'LIBERACIONES']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ControlPedidos/Cedis/Index', false)
                ->where('liberaciones.data', function ($filas) use ($tareaPropia, $tareaAjena) {
                    $ids = collect($filas)->pluck('id')->all();

                    return in_array($tareaPropia->id, $ids, true)
                        && ! in_array($tareaAjena->id, $ids, true);
                }));
    }

    /** @return array{0: Departamento, 1: Departamento} */
    private function departamentos(): array
    {
        return [
            Departamento::create(['nombre' => 'Aromas '.uniqid(), 'activo' => true]),
            Departamento::create(['nombre' => 'Bellaroma '.uniqid(), 'activo' => true]),
        ];
    }

    private function usuarioCedis(?int $principal, array $adicionales = []): User
    {
        Permission::findOrCreate('control_pedidos.cedis', 'web');
        $usuario = User::factory()->create(['departamento_id' => $principal]);
        $ids = array_values(array_filter(array_merge(
            $principal ? [$principal] : [],
            $adicionales
        )));
        if ($ids !== []) {
            $usuario->departamentos()->sync($ids);
        }
        $usuario->givePermissionTo('control_pedidos.cedis');

        return $usuario;
    }

    private function pedidoEnCedis(User $vendedor, string $folio): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()
            ->where('fase_ciclo', CatalogoEstatusPedido::FASE_EN_CEDIS)
            ->first()
            ?? CatalogoEstatusPedido::create([
                'codigo_interno' => 'EN_CEDIS_VIS_'.uniqid(),
                'nombre_visual' => 'En CEDIS',
                'color_hex' => '#EAB308',
                'fase_ciclo' => CatalogoEstatusPedido::FASE_EN_CEDIS,
                'orden' => 3,
                'activo' => true,
            ]);

        $pedido = PedidoBma::create([
            'folio' => $folio.'-'.uniqid(),
            'folio_remision' => $folio.'-REM',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $vendedor->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
            'pago_validado_at' => now(),
        ]);

        PedidoBmaDocumento::create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_REMISION,
            'ruta_archivo' => 'test/remision.pdf',
            'nombre_original' => 'remision.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 100,
            'orden' => 0,
            'activo' => true,
        ]);

        return $pedido;
    }
}
