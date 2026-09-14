<?php

namespace Tests\Feature\Tiendanube;

use App\Jobs\Tiendanube\SyncTiendanubeCatalogoJob;
use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioFuenteVersion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class TiendanubePrecioCatalogoSeleccionTest extends TestCase
{
    use RefreshDatabaseSafe;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => 8004291,
            'app_id' => '37163',
            'access_token' => Crypt::encryptString('token-test'),
        ])->save();

        foreach ([
            'tiendanube.ver',
            'tiendanube.sincronizar',
            'tiendanube.precios.ver',
            'tiendanube.precios.editar',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            'tiendanube.ver',
            'tiendanube.sincronizar',
            'tiendanube.precios.ver',
            'tiendanube.precios.editar',
        ]);

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_catalogo_vacio_inicia_sync_y_segunda_apertura_no_duplica_job(): void
    {
        Queue::fake();
        Http::fake();

        $this->actingAs($this->user)
            ->get(route('tiendanube.precios.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('catalogo.estado', 'sin_primera_carga')
            );

        Queue::assertNothingPushed();

        $inicio = $this->actingAs($this->user)->postJson(route('tiendanube.sincronizar'));
        $inicio->assertOk();
        $logId = $inicio->json('sync_log_id');
        Queue::assertPushed(SyncTiendanubeCatalogoJob::class, 1);

        $this->actingAs($this->user)
            ->get(route('tiendanube.precios.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sync.proceso_activo.id', $logId)
            );

        $this->actingAs($this->user)
            ->postJson(route('tiendanube.sincronizar'))
            ->assertStatus(409);

        Queue::assertPushed(SyncTiendanubeCatalogoJob::class, 1);
    }

    public function test_listado_muestra_variantes_reales_sin_captura_y_sin_http_remoto(): void
    {
        $this->sembrarCatalogo();
        Http::fake();

        $res = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.catalogo.listar', ['per_page' => 50]));

        $res->assertOk()
            ->assertJsonPath('estado', 'listo')
            ->assertJsonPath('conteos.productos_distintos', 4)
            ->assertJsonPath('conteos.variantes_total', 5);

        $ids = collect($res->json('data'))->pluck('variante_id')->sort()->values()->all();
        $this->assertSame([1000, 1001, 2000, 3000, 4000], $ids);

        $fila = collect($res->json('data'))->firstWhere('variante_id', 1000);
        $this->assertSame('Perfume', $fila['nombre']);
        $this->assertSame('SKU-A', $fila['sku']);
        $this->assertSame('50.00', $fila['precio_normal']);
        $this->assertSame('40.00', $fila['precio_promocional']);
        $this->assertFalse($fila['sin_promocion']);
        $this->assertSame('10.00', $fila['costo_remoto']);
        $this->assertSame('12.00', $fila['costo_local']);

        $sinPromo = collect($res->json('data'))->firstWhere('variante_id', 1001);
        $this->assertTrue($sinPromo['sin_promocion']);

        $sinCosto = collect($res->json('data'))->firstWhere('variante_id', 1001);
        $this->assertTrue($sinCosto['sin_costo_remoto']);

        $cero = collect($res->json('data'))->firstWhere('variante_id', 3000);
        $this->assertSame('0.00', $cero['costo_remoto']);
        $this->assertFalse($cero['sin_costo_remoto']);
        $this->assertTrue($cero['costo_remoto_cero']);

        Http::assertNothingSent();
    }

    public function test_filtros_categoria_rango_y_sin_duplicar_variantes(): void
    {
        $this->sembrarCatalogo();

        $exacta = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.catalogo.listar', [
                'categoria_ids' => [10],
                'per_page' => 50,
            ]));
        $exacta->assertOk();
        $this->assertSame([1000, 1001], collect($exacta->json('data'))->pluck('variante_id')->sort()->values()->all());
        $this->assertSame(1, $exacta->json('conteos.productos_distintos'));

        $conHijas = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.catalogo.listar', [
                'categoria_ids' => [10],
                'incluir_subcategorias' => 1,
                'per_page' => 50,
            ]));
        $this->assertSame([1000, 1001, 2000], collect($conHijas->json('data'))->pluck('variante_id')->sort()->values()->all());

        $multi = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.catalogo.listar', [
                'categoria_ids' => [10, 20],
                'per_page' => 50,
            ]));
        $this->assertSame([1000, 1001], collect($multi->json('data'))->pluck('variante_id')->sort()->values()->all());

        $sinCat = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.catalogo.listar', [
                'sin_categoria' => 1,
                'per_page' => 50,
            ]));
        $this->assertSame([3000, 4000], collect($sinCat->json('data'))->pluck('variante_id')->sort()->values()->all());

        $rango = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.catalogo.listar', [
                'precio_min' => 40,
                'precio_max' => 60,
                'per_page' => 50,
            ]));
        $this->assertSame([1000], collect($rango->json('data'))->pluck('variante_id')->all());
    }

    public function test_seleccion_multipagina_todos_los_resultados_exclusion_y_producto_nuevo(): void
    {
        $this->sembrarCatalogo();

        $pagina1 = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.catalogo.listar', ['per_page' => 2, 'page' => 1, 'sort' => 'id']));
        $idPagina1 = $pagina1->json('data.0.variante_id');

        $created = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.selecciones.store'), [
                'modo' => 'pagina',
                'variante_ids' => [$idPagina1],
                'page_variante_ids' => [$idPagina1],
                'filtros' => [],
            ])
            ->assertCreated();

        $selectionId = $created->json('selection_id');
        $version = $created->json('version');
        $this->assertContains($idPagina1, $created->json('seleccionados_pagina'));

        $pagina2 = $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.catalogo.listar', [
                'per_page' => 2,
                'page' => 2,
                'sort' => 'id',
                'selection_id' => $selectionId,
            ]));
        $idPagina2 = $pagina2->json('data.0.variante_id');
        $this->assertNotSame($idPagina1, $idPagina2);

        $updated = $this->actingAs($this->user)
            ->patchJson(route('tiendanube.precios.selecciones.update', $selectionId), [
                'version' => $version,
                'accion' => 'agregar',
                'variante_ids' => [$idPagina2],
                'page_variante_ids' => [$idPagina2],
            ])
            ->assertOk();

        $todos = $this->actingAs($this->user)
            ->patchJson(route('tiendanube.precios.selecciones.update', $selectionId), [
                'version' => $updated->json('version'),
                'accion' => 'seleccionar_todos',
                'filtros' => [],
            ])
            ->assertOk();

        $this->assertSame(5, $todos->json('total_variantes'));
        $this->assertSame('todos_resultados', $todos->json('modo'));

        $despuesExclusion = $this->actingAs($this->user)
            ->patchJson(route('tiendanube.precios.selecciones.update', $selectionId), [
                'version' => $todos->json('version'),
                'accion' => 'quitar',
                'variante_ids' => [1001],
            ])
            ->assertOk();
        $this->assertSame(4, $despuesExclusion->json('total_variantes'));

        $this->crearProducto(500, 'Nuevo', 5000, 'SKU-NEW', '9.00', null, null, []);

        $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.selecciones.show', $selectionId))
            ->assertOk()
            ->assertJsonPath('total_variantes', 4);

        $resolver = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.selecciones.resolver', $selectionId), [
                'per_page' => 100,
            ]);
        $resolver->assertOk();
        $ids = $resolver->json('variante_ids');
        $this->assertNotContains(1001, $ids);
        $this->assertNotContains(5000, $ids);
        $this->assertCount(4, $ids);
    }

    public function test_variante_eliminada_se_marca_invalida_y_conflicto_de_version(): void
    {
        $this->sembrarCatalogo();

        $created = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.selecciones.store'), [
                'modo' => 'pagina',
                'variante_ids' => [2000],
            ])
            ->assertCreated();

        TiendanubeProductoVariante::query()->whereKey(2000)->delete();

        $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.selecciones.show', $created->json('selection_id')))
            ->assertOk()
            ->assertJsonPath('miembros_invalidos', 1);

        $this->actingAs($this->user)
            ->patchJson(route('tiendanube.precios.selecciones.update', $created->json('selection_id')), [
                'version' => 999,
                'accion' => 'vaciar',
            ])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'conflicto');
    }

    public function test_seleccion_no_es_consumible_por_otro_usuario_ni_otra_tienda(): void
    {
        $this->sembrarCatalogo();

        $created = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.selecciones.store'), [
                'modo' => 'pagina',
                'variante_ids' => [1000],
            ])
            ->assertCreated();

        $otro = User::factory()->create();
        $otro->givePermissionTo(['tiendanube.ver']);

        $this->actingAs($otro)
            ->getJson(route('tiendanube.precios.selecciones.show', $created->json('selection_id')))
            ->assertForbidden();

        TiendanubeConfiguracion::obtener()->fill(['store_id' => 999])->save();

        $this->actingAs($this->user)
            ->getJson(route('tiendanube.precios.selecciones.show', $created->json('selection_id')))
            ->assertForbidden();
    }

    public function test_sin_permiso_de_costo_oculta_valores_y_rechaza_filtro(): void
    {
        $this->sembrarCatalogo();

        $soloVer = User::factory()->create();
        $soloVer->givePermissionTo(['tiendanube.ver']);

        $res = $this->actingAs($soloVer)
            ->getJson(route('tiendanube.precios.catalogo.listar', ['per_page' => 50]));
        $res->assertOk();
        $fila = $res->json('data.0');
        $this->assertArrayNotHasKey('costo_remoto', $fila);
        $this->assertArrayNotHasKey('costo_local', $fila);

        $this->actingAs($soloVer)
            ->getJson(route('tiendanube.precios.catalogo.listar', ['costo' => 'con']))
            ->assertStatus(422);
    }

    public function test_reemplazar_filtros_no_agrega_resultados_nuevos(): void
    {
        $this->sembrarCatalogo();

        $created = $this->actingAs($this->user)
            ->postJson(route('tiendanube.precios.selecciones.store'), [
                'modo' => 'todos_resultados',
                'filtros' => ['categoria_ids' => [10]],
            ])
            ->assertCreated();
        $this->assertSame(2, $created->json('total_variantes'));

        $reemplazo = $this->actingAs($this->user)
            ->patchJson(route('tiendanube.precios.selecciones.update', $created->json('selection_id')), [
                'version' => $created->json('version'),
                'accion' => 'reemplazar',
                'modo' => 'pagina',
                'variante_ids' => [],
                'filtros' => ['q' => 'NuevoNombreQueNoExiste'],
            ])
            ->assertOk();

        $this->assertSame(0, $reemplazo->json('total_variantes'));
        $this->assertSame(2, $reemplazo->json('generacion'));
        $this->assertGreaterThan($created->json('version'), $reemplazo->json('version'));
    }

    private function sembrarCatalogo(): void
    {
        TiendanubeCategoria::query()->create(['id' => 10, 'name' => ['es' => 'Padre'], 'parent_id' => null]);
        TiendanubeCategoria::query()->create(['id' => 11, 'name' => ['es' => 'Hija'], 'parent_id' => 10]);
        TiendanubeCategoria::query()->create(['id' => 20, 'name' => ['es' => 'Otra'], 'parent_id' => null]);

        $this->crearProducto(100, 'Perfume', 1000, 'SKU-A', '50.00', '40.00', '10.00', [10, 20], ['Negro']);
        $this->crearVariante(1001, 100, 'SKU-B', '80.00', null, null, ['Rojo']);
        $this->crearProducto(200, 'Hija prod', 2000, 'SKU-C', '200.00', null, '5.00', [11]);
        $this->crearProducto(300, 'Huérfano', 3000, 'SKU-D', '15.00', null, '0.00', []);
        $this->crearProducto(400, 'Extra', 4000, 'SKU-E', '90.00', null, null, []);

        TiendanubePrecioFuenteVersion::query()->create([
            'store_id' => 8004291,
            'tipo' => TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL,
            'fuente_clave' => TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL,
            'variante_id' => 1000,
            'producto_id' => 100,
            'version' => 1,
            'moneda' => 'MXN',
            'valor_decimal' => '12.00',
            'fecha' => now(),
            'origen' => TiendanubePrecioFuenteVersion::ORIGEN_MANUAL,
        ]);
    }

    /**
     * @param  list<int>  $categoriaIds
     * @param  list<string>  $valores
     */
    private function crearProducto(
        int $productoId,
        string $nombre,
        int $varianteId,
        string $sku,
        string $precio,
        ?string $promo,
        ?string $costo,
        array $categoriaIds,
        array $valores = [],
    ): void {
        $producto = TiendanubeProducto::query()->create([
            'id' => $productoId,
            'name' => ['es' => $nombre],
            'published' => true,
            'synced_at' => now(),
        ]);
        if ($categoriaIds !== []) {
            $producto->categorias()->attach($categoriaIds);
        }
        $this->crearVariante($varianteId, $productoId, $sku, $precio, $promo, $costo, $valores);
    }

    /**
     * @param  list<string>  $valores
     */
    private function crearVariante(
        int $id,
        int $productoId,
        string $sku,
        string $precio,
        ?string $promo,
        ?string $costo,
        array $valores = [],
    ): void {
        TiendanubeProductoVariante::query()->create([
            'id' => $id,
            'producto_id' => $productoId,
            'sku' => $sku,
            'price' => $precio,
            'promotional_price' => $promo,
            'cost' => $costo,
            'values' => $valores,
        ]);
    }
}
