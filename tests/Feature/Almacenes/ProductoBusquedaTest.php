<?php

namespace Tests\Feature\Almacenes;

use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoCosto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductoBusquedaTest extends TestCase
{
    use RefreshDatabase;

    protected User $usuario;

    protected Sucursal $sucursalOperable;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'gestion_interna.productos.ver',
            'almacenes.productos.ver',
            'almacenes.inventarios.ver',
            'almacenes.costos.ver',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'gestion_interna.productos.ver',
            'almacenes.inventarios.ver',
            'almacenes.costos.ver',
        ]);

        $this->sucursalOperable = Sucursal::factory()->create(['nombre' => 'Sucursal Busqueda']);
        $this->usuario->concederAccesoSucursal($this->sucursalOperable, esPrincipal: true);
    }

    public function test_buscar_por_sku_descripcion_codigo_barras_y_folio(): void
    {
        $this->crearProducto(['sku' => '42', 'descripcion' => 'Perfume Zeta', 'codigo_barras' => '7501234567890', 'folio' => 100100]);
        $this->crearProducto(['sku' => '99', 'descripcion' => 'Otro artículo', 'folio' => 100101]);

        $this->actingAs($this->usuario)
            ->getJson(route('gestion_interna.productos.buscar', ['q' => '42']))
            ->assertOk()
            ->assertJsonPath('data.0.sku', '42');

        $this->actingAs($this->usuario)
            ->getJson(route('gestion_interna.productos.buscar', ['q' => 'Zeta']))
            ->assertOk()
            ->assertJsonPath('data.0.descripcion', 'Perfume Zeta');

        $this->actingAs($this->usuario)
            ->getJson(route('gestion_interna.productos.buscar', ['q' => '7501234']))
            ->assertOk()
            ->assertJsonPath('data.0.codigo_barras', '7501234567890');

        $this->actingAs($this->usuario)
            ->getJson(route('gestion_interna.productos.buscar', ['q' => '100100']))
            ->assertOk()
            ->assertJsonPath('data.0.folio', 100100);
    }

    public function test_buscar_pagina_dos_devuelve_otro_lote(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->crearProducto([
                'sku' => 'LOTE' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'descripcion' => 'Producto lote ' . $i,
                'folio' => 200000 + $i,
            ]);
        }

        $paginaUno = $this->actingAs($this->usuario)
            ->getJson(route('gestion_interna.productos.buscar', ['per_page' => 25, 'page' => 1]))
            ->assertOk()
            ->json('data');

        $paginaDos = $this->actingAs($this->usuario)
            ->getJson(route('gestion_interna.productos.buscar', ['per_page' => 25, 'page' => 2]))
            ->assertOk()
            ->json('data');

        $this->assertCount(25, $paginaUno);
        $this->assertCount(5, $paginaDos);
        $this->assertNotEquals($paginaUno[0]['id'], $paginaDos[0]['id']);
    }

    public function test_buscar_nombre_partido_y_lectura_con_prefijo_de_pistola(): void
    {
        $this->crearProducto([
            'sku' => '6295199813966',
            'descripcion' => 'C BODY ARMAF ODYSSEY BA HA MAS 200ML.',
            'codigo_barras' => '6295199813966',
        ]);

        $this->actingAs($this->usuario)
            ->getJson(route('gestion_interna.productos.buscar', ['q' => 'bahamas']))
            ->assertOk()
            ->assertJsonPath('data.0.sku', '6295199813966');

        $this->actingAs($this->usuario)
            ->getJson(route('gestion_interna.productos.buscar', ['q' => 'baha mas']))
            ->assertOk()
            ->assertJsonPath('data.0.sku', '6295199813966');

        $this->actingAs($this->usuario)
            ->getJson(route('gestion_interna.productos.buscar', ['q' => ']E06295199813966']))
            ->assertOk()
            ->assertJsonPath('data.0.codigo_barras', '6295199813966');
    }

    public function test_buscar_en_almacen_incluye_producto_sin_fila_de_inventario(): void
    {
        $almacen = $this->crearAlmacen();
        $almacen->update(['permite_busqueda_productos' => true]);
        $conFila = $this->crearProducto(['sku' => 'CON-FILA', 'descripcion' => 'Crema con existencia']);
        $sinFila = $this->crearProducto(['sku' => 'SIN-FILA', 'descripcion' => 'Crema sin fila', 'codigo_barras' => '7500000000018']);
        Inventario::create(['producto_id' => $conFila->id, 'almacen_id' => $almacen->id, 'existencia' => 1, 'apartado' => 0]);

        $this->actingAs($this->usuario)
            ->getJson(route('gestion_interna.productos.buscar', ['q' => 'Crema sin fila', 'almacen_id' => $almacen->id]))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($this->usuario)
            ->getJson(route('gestion_interna.productos.buscar', ['q' => 'SIN-FILA', 'almacen_id' => $almacen->id]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $sinFila->id)
            ->assertJsonPath('data.0.existencia', null);

        $this->actingAs($this->usuario)
            ->getJson(route('gestion_interna.productos.buscar', [
                'q' => '7500000000018',
                'almacen_id' => $almacen->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $sinFila->id)
            ->assertJsonPath('data.0.existencia', null);
    }

    public function test_buscar_requiere_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('gestion_interna.productos.buscar'))
            ->assertForbidden();
    }

    public function test_costos_ordenan_por_producto_y_costo(): void
    {
        Permission::findOrCreate('almacenes.costos.ver', 'web');
        $almacen = $this->crearAlmacen();

        $productoA = $this->crearProducto(['sku' => 'A1', 'descripcion' => 'AAA Producto', 'folio' => 300001]);
        $productoZ = $this->crearProducto(['sku' => 'Z9', 'descripcion' => 'ZZZ Producto', 'folio' => 300002]);

        ProductoCosto::create(['producto_id' => $productoZ->id, 'almacen_id' => $almacen->id, 'costo' => 5]);
        ProductoCosto::create(['producto_id' => $productoA->id, 'almacen_id' => $almacen->id, 'costo' => 50]);

        $this->actingAs($this->usuario)
            ->get(route('almacenes.costos.index', ['sort' => 'producto', 'dir' => 'asc']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('costos.data', 2)
                ->where('costos.data.0.producto.descripcion', 'AAA Producto')
            );

        $this->actingAs($this->usuario)
            ->get(route('almacenes.costos.index', ['sort' => 'costo', 'dir' => 'desc']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('costos.data.0.costo', '50.00')
            );
    }

    public function test_inventarios_ordenan_por_existencia_desc(): void
    {
        $almacen = $this->crearAlmacen();

        $bajo = $this->crearProducto(['sku' => 'BAJO', 'descripcion' => 'Stock bajo', 'folio' => 400001]);
        $alto = $this->crearProducto(['sku' => 'ALTO', 'descripcion' => 'Stock alto', 'folio' => 400002]);

        Inventario::create(['producto_id' => $bajo->id, 'almacen_id' => $almacen->id, 'existencia' => 2, 'apartado' => 0]);
        Inventario::create(['producto_id' => $alto->id, 'almacen_id' => $almacen->id, 'existencia' => 99, 'apartado' => 0]);

        $this->actingAs($this->usuario)
            ->get(route('almacenes.inventarios.index', ['sort' => 'existencia', 'dir' => 'desc']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('inventarios.data', 2)
                ->where('inventarios.data.0.existencia', '99.000')
            );
    }

    private function crearProducto(array $attrs = []): Producto
    {
        static $folio = 500000;
        $folio++;

        return Producto::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'sku' => 'SKU' . $folio,
            'descripcion' => 'Producto test ' . $folio,
            'activo' => true,
        ], $attrs));
    }

    private function crearAlmacen(): Almacen
    {
        static $n = 0;
        $n++;

        return Almacen::create([
            'codigo' => 'ALM' . $n,
            'nombre' => 'Almacén Test ' . $n,
            'sucursal_id' => $this->sucursalOperable->id,
            'activo' => true,
        ]);
    }
}
