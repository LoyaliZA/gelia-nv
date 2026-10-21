<?php

namespace Tests\Feature\Almacenes;

use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\ProductoCosto;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Almacenes\AlcanceAlmacenesService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class CoberturaConsultaTest extends TestCase
{
    use RefreshDatabaseSafe;

    public function createApplication()
    {
        $app = parent::createApplication();
        $conexion = $app['config']->get('database.default');
        $base = $app['config']->get('database.connections.'.$conexion.'.database');

        if ($conexion !== 'sqlite' || $base !== ':memory:') {
            throw new \RuntimeException('La conexión de prueba no está aislada de la base de operación.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        foreach ([
            'almacenes.inventarios.ver',
            'almacenes.inventarios.gestionar',
            'almacenes.costos.ver',
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    public function test_distingue_ausencia_existencia_nula_y_cero_sin_escribir(): void
    {
        [$user, $sucursal, $almacen] = $this->operadorUnaSucursal();
        $sinFila = $this->crearProducto(['sku' => 'ZSKU-AUSENCIA', 'descripcion' => 'Producto ausencia inventario']);
        $nula = $this->crearProducto(['sku' => 'ZSKU-NULA', 'descripcion' => 'Producto existencia nula']);
        $cero = $this->crearProducto(['sku' => 'ZSKU-CERO', 'descripcion' => 'Producto existencia cero']);
        $asignado = $this->crearProducto(['sku' => 'ZSKU-ASIGNADO', 'descripcion' => 'Producto solo asignacion']);

        Inventario::create([
            'producto_id' => $nula->id,
            'almacen_id' => $almacen->id,
            'existencia' => null,
            'apartado' => 0,
        ]);
        Inventario::create([
            'producto_id' => $cero->id,
            'almacen_id' => $almacen->id,
            'existencia' => 0,
            'apartado' => 0,
        ]);
        ProductoAlmacen::create([
            'producto_id' => $asignado->id,
            'almacen_id' => $almacen->id,
            'ubicacion' => 'A-1',
            'activo_en_almacen' => true,
            'origen_asignacion' => 'manual',
        ]);

        $antes = $this->conteos();

        $this->assertEstado($user, $almacen, $sucursal, 'ZSKU-AUSENCIA', [
            'tiene_inventario' => false,
            'tiene_cantidad' => false,
            'existencia' => null,
            'asignado' => false,
        ]);
        $this->assertEstado($user, $almacen, $sucursal, 'ZSKU-NULA', [
            'tiene_inventario' => true,
            'tiene_cantidad' => false,
            'existencia' => null,
            'asignado' => false,
        ]);
        $this->assertEstado($user, $almacen, $sucursal, 'ZSKU-CERO', [
            'tiene_inventario' => true,
            'tiene_cantidad' => true,
            'existencia' => '0.000',
            'asignado' => false,
        ]);
        $this->assertEstado($user, $almacen, $sucursal, 'ZSKU-ASIGNADO', [
            'tiene_inventario' => false,
            'tiene_cantidad' => false,
            'existencia' => null,
            'asignado' => true,
        ]);

        $this->assertSame($antes, $this->conteos());
        $this->assertSame($sinFila->id, Producto::where('sku', 'ZSKU-AUSENCIA')->value('id'));
    }

    public function test_asignar_crea_solo_la_pareja_faltante(): void
    {
        [$user, , $almacen] = $this->operadorUnaSucursal();
        $existente = $this->crearProducto(['sku' => 'YA', 'descripcion' => 'Ya asignado']);
        $nuevo = $this->crearProducto(['sku' => 'NUEVO', 'descripcion' => 'Por asignar']);
        ProductoAlmacen::create([
            'producto_id' => $existente->id,
            'almacen_id' => $almacen->id,
            'ubicacion' => 'A-1',
            'activo_en_almacen' => true,
            'origen_asignacion' => 'importacion',
        ]);

        $inventarios = Inventario::count();
        $costos = ProductoCosto::count();

        $this->actingAs($user)
            ->post(route('almacenes.cobertura.asignar'), [
                'almacen_id' => $almacen->id,
                'producto_ids' => [$existente->id, $nuevo->id],
                'ubicacion' => 'B-9',
            ])
            ->assertRedirect();

        $pareja = ProductoAlmacen::where('producto_id', $existente->id)->where('almacen_id', $almacen->id)->first();
        $this->assertSame('A-1', $pareja->ubicacion);
        $this->assertSame('importacion', $pareja->origen_asignacion);
        $this->assertTrue($pareja->activo_en_almacen);

        $creada = ProductoAlmacen::where('producto_id', $nuevo->id)->where('almacen_id', $almacen->id)->first();
        $this->assertNotNull($creada);
        $this->assertSame('B-9', $creada->ubicacion);
        $this->assertSame(1, ProductoAlmacen::where('producto_id', $existente->id)->count());
        $this->assertSame($inventarios, Inventario::count());
        $this->assertSame($costos, ProductoCosto::count());
    }

    public function test_rechaza_par_incoherente_y_almacen_fuera_de_alcance(): void
    {
        [$user, $sucursalA, $almacenA, $sucursalB, $almacenB] = $this->operadorDosSucursales();
        $rutas = [
            'almacenes.cobertura.index',
            'almacenes.catalogo.index',
            'almacenes.inventarios.index',
            'almacenes.costos.index',
        ];

        foreach ($rutas as $ruta) {
            $this->from('/almacenes')
                ->actingAs($user)
                ->get(route($ruta, [
                    'sucursal_id' => $sucursalA->id,
                    'almacen_id' => $almacenB->id,
                ]))
                ->assertSessionHasErrors('almacen_id');
        }

        [$restringido, $propia, $ajena] = $this->operadorUnaSucursalConAjena();
        foreach ($rutas as $ruta) {
            $this->actingAs($restringido)
                ->get(route($ruta, ['almacen_id' => $ajena->id]))
                ->assertForbidden();
            $this->actingAs($restringido)
                ->get(route($ruta, [
                    'sucursal_id' => $propia->id,
                    'almacen_id' => $ajena->id,
                ]))
                ->assertForbidden();
        }
    }

    public function test_sin_filtros_conserva_el_default_y_el_enlace_directo_reconstruye_la_consulta(): void
    {
        [$user, $sucursalA, $almacenA, $sucursalB, $almacenB] = $this->operadorDosSucursales();
        Inventario::create([
            'producto_id' => $this->crearProducto(['sku' => 'INV-A', 'descripcion' => 'Inventario A'])->id,
            'almacen_id' => $almacenA->id,
            'existencia' => 3,
            'apartado' => 0,
        ]);
        Inventario::create([
            'producto_id' => $this->crearProducto(['sku' => 'INV-B', 'descripcion' => 'Inventario B'])->id,
            'almacen_id' => $almacenB->id,
            'existencia' => 4,
            'apartado' => 0,
        ]);

        $this->withSession([AlcanceAlmacenesService::SESSION_SUCURSAL_ACTIVA => $sucursalB->id])
            ->actingAs($user)
            ->get(route('almacenes.catalogo.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filtros.sucursal_id', (string) $sucursalB->id)
                ->has('almacenes', 1)
                ->where('almacenes.0.id', $almacenB->id)
            );

        $this->withSession([AlcanceAlmacenesService::SESSION_SUCURSAL_ACTIVA => $sucursalB->id])
            ->actingAs($user)
            ->get(route('almacenes.inventarios.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('filtros.sucursal_id')
                ->missing('filtros.almacen_id')
                ->has('inventarios.data', 2)
            );

        $this->withSession([AlcanceAlmacenesService::SESSION_SUCURSAL_ACTIVA => $sucursalB->id])
            ->actingAs($user)
            ->get(route('almacenes.cobertura.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('filtros.almacen_id')
                ->has('almacenes', 2)
            );

        $producto = $this->crearProducto(['sku' => 'DIRECTO', 'descripcion' => 'Consulta directa']);
        $this->actingAs($user)
            ->get(route('almacenes.cobertura.index', [
                'sucursal_id' => $sucursalA->id,
                'almacen_id' => $almacenA->id,
                'q' => 'DIRECTO',
                'asignacion' => 'sin',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filtros.sucursal_id', (string) $sucursalA->id)
                ->where('filtros.almacen_id', (string) $almacenA->id)
                ->where('filtros.q', 'DIRECTO')
                ->where('filtros.asignacion', 'sin')
                ->where('filas.data.0.id', $producto->id)
                ->where('filas.data.0.tiene_inventario', false)
            );

        $this->actingAs($user)
            ->get('/almacenes/productos')
            ->assertRedirect('/gestion-interna/productos');
    }

    private function assertEstado(User $user, Almacen $almacen, Sucursal $sucursal, string $sku, array $esperado): void
    {
        $this->actingAs($user)
            ->get(route('almacenes.cobertura.index', [
                'sucursal_id' => $sucursal->id,
                'almacen_id' => $almacen->id,
                'q' => $sku,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('filas.data', 1)
                ->where('filas.data.0.sku', $sku)
                ->where('filas.data.0.tiene_inventario', $esperado['tiene_inventario'])
                ->where('filas.data.0.tiene_cantidad', $esperado['tiene_cantidad'])
                ->where('filas.data.0.existencia', $esperado['existencia'])
                ->where('filas.data.0.asignado', $esperado['asignado'])
            );
    }

    /**
     * @return array{0: User, 1: Sucursal, 2: Almacen}
     */
    private function operadorUnaSucursal(): array
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            'almacenes.inventarios.ver',
            'almacenes.inventarios.gestionar',
            'almacenes.costos.ver',
        ]);
        $sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal propia']);
        $user->concederAccesoSucursal($sucursal, esPrincipal: true);
        $almacen = $this->crearAlmacen($sucursal, 'PROPIA');

        return [$user, $sucursal, $almacen];
    }

    /**
     * @return array{0: User, 1: Sucursal, 2: Almacen}
     */
    private function operadorUnaSucursalConAjena(): array
    {
        [$user, $sucursal] = $this->operadorUnaSucursal();
        $ajenaSucursal = Sucursal::factory()->create(['nombre' => 'Sucursal ajena']);
        $ajena = $this->crearAlmacen($ajenaSucursal, 'AJENA');

        return [$user, $sucursal, $ajena];
    }

    /**
     * @return array{0: User, 1: Sucursal, 2: Almacen, 3: Sucursal, 4: Almacen}
     */
    private function operadorDosSucursales(): array
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            'almacenes.inventarios.ver',
            'almacenes.inventarios.gestionar',
            'almacenes.costos.ver',
        ]);
        $sucursalA = Sucursal::factory()->create(['nombre' => 'Sucursal A']);
        $sucursalB = Sucursal::factory()->create(['nombre' => 'Sucursal B']);
        $user->concederAccesoSucursal($sucursalA, esPrincipal: true);
        $user->concederAccesoSucursal($sucursalB);
        $almacenA = $this->crearAlmacen($sucursalA, 'A');
        $almacenB = $this->crearAlmacen($sucursalB, 'B');

        return [$user, $sucursalA, $almacenA, $sucursalB, $almacenB];
    }

    private function crearAlmacen(Sucursal $sucursal, string $codigo): Almacen
    {
        return Almacen::create([
            'sucursal_id' => $sucursal->id,
            'codigo' => 'ALM-'.$codigo,
            'nombre' => 'Almacén '.$codigo,
            'activo' => true,
        ]);
    }

    private function crearProducto(array $attrs = []): Producto
    {
        static $folio = 810000;
        $folio++;

        return Producto::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'sku' => 'SKU'.$folio,
            'descripcion' => 'Producto '.$folio,
            'activo' => true,
        ], $attrs));
    }

    /**
     * @return array<string, int>
     */
    private function conteos(): array
    {
        return [
            'productos' => Producto::count(),
            'producto_almacen' => ProductoAlmacen::count(),
            'inventarios' => Inventario::count(),
            'producto_costos' => ProductoCosto::count(),
            'almacenes' => Almacen::count(),
        ];
    }
}
