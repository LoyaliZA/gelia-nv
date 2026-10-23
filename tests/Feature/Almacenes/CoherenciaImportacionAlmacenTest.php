<?php

namespace Tests\Feature\Almacenes;

use App\Models\Almacen;
use App\Models\Almacenes\ImportacionAlmacenLog;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\ProductoCosto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class CoherenciaImportacionAlmacenTest extends TestCase
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
            'almacenes.inventarios.importar',
            'almacenes.costos.importar',
            'gestion_interna.productos.importar',
            'gestion_interna.productos.gestionar',
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    protected function tearDown(): void
    {
        Storage::deleteDirectory('importaciones_almacenes/fase4');
        parent::tearDown();
    }

    public function test_preset_inventario_abre_carga_completa_solo_si_puede_las_cuatro_operaciones(): void
    {
        $completa = $this->usuario([
            'almacenes.inventarios.ver',
            'almacenes.inventarios.importar',
            'almacenes.costos.importar',
            'gestion_interna.productos.importar',
        ]);

        $this->actingAs($completa)
            ->get(route('almacenes.importaciones.index', ['preset' => 'inventario']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('caso_inicial', 'completa')
                ->has('casos', 4)
                ->where('casos.3.id', 'completa')
                ->where('casos.3.titulo', 'Carga completa')
            );

        $this->actingAs($completa)
            ->get(route('almacenes.inventarios.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('puede_carga_completa', true));

        $parcial = $this->usuario([
            'almacenes.inventarios.ver',
            'almacenes.inventarios.importar',
        ]);

        $this->actingAs($parcial)
            ->get(route('almacenes.importaciones.index', ['preset' => 'inventario']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('caso_inicial', 'existencias')
                ->has('casos', 1)
                ->where('casos.0.id', 'existencias')
            );

        $this->actingAs($parcial)
            ->get(route('almacenes.inventarios.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('puede_carga_completa', false));
    }

    public function test_simular_existencias_no_cambia_el_catalogo(): void
    {
        $user = $this->usuario(['almacenes.inventarios.importar']);
        $sucursal = Sucursal::factory()->create();
        $user->concederAccesoSucursal($sucursal, esPrincipal: true);
        $almacen = $this->almacen($sucursal);
        $producto = $this->producto(920001);
        Inventario::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'existencia' => 4,
            'apartado' => 1,
            'ubicacion' => 'ANAQUEL',
        ]);
        ProductoCosto::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'costo' => 8,
            'precio_venta' => 12,
        ]);
        ProductoAlmacen::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'ubicacion' => 'ASIG',
            'activo_en_almacen' => true,
            'origen_asignacion' => 'manual',
        ]);

        $ruta = 'importaciones_almacenes/fase4/existencias.csv';
        Storage::put($ruta, "folio,existencia\n920001,9\n");
        $lote = ImportacionAlmacenLog::create([
            'user_id' => $user->id,
            'tipo' => 'hub',
            'almacen_id' => $almacen->id,
            'archivo_ruta' => $ruta,
            'mapping' => ['folio' => 'folio', 'existencia' => 'existencia'],
            'operaciones' => ['cantidades_referencia'],
            'estado' => 'borrador',
        ]);

        $this->actingAs($user)
            ->postJson(route('almacenes.importaciones.simular', $lote), [
                'mapping' => ['folio' => 'folio', 'existencia' => 'existencia'],
            ])
            ->assertOk();

        $producto->refresh();
        $this->assertSame('SKU920001', $producto->sku);
        $this->assertSame('Producto 920001', $producto->descripcion);
        $this->assertDatabaseHas('inventarios', [
            'producto_id' => $producto->id,
            'existencia' => '4.000',
            'apartado' => '1.000',
            'ubicacion' => 'ANAQUEL',
        ]);
        $this->assertDatabaseHas('producto_costos', [
            'producto_id' => $producto->id,
            'costo' => '8.00',
        ]);
        $this->assertDatabaseHas('producto_almacen', [
            'producto_id' => $producto->id,
            'ubicacion' => 'ASIG',
        ]);
        $this->assertSame('simulado', $lote->fresh()->estado);
    }

    public function test_aplicar_costos_sin_el_permiso_de_la_operacion_no_escribe(): void
    {
        $user = $this->usuario(['gestion_interna.productos.gestionar']);
        $sucursal = Sucursal::factory()->create();
        $user->concederAccesoSucursal($sucursal, esPrincipal: true);
        $almacen = $this->almacen($sucursal);
        $producto = $this->producto(920002);
        $lote = ImportacionAlmacenLog::create([
            'user_id' => $user->id,
            'tipo' => 'hub',
            'almacen_id' => $almacen->id,
            'archivo_ruta' => 'importaciones_almacenes/fase4/no-aplica.csv',
            'mapping' => ['folio' => 'folio', 'costo' => 'costo'],
            'operaciones' => ['costos_precios'],
            'estado' => 'simulado',
            'resumen_simulacion' => ['total_filas' => 1],
        ]);

        $this->actingAs($user)
            ->postJson(route('almacenes.importaciones.aplicar', $lote), [
                'mapping' => ['folio' => 'folio', 'costo' => 'costo'],
            ])
            ->assertStatus(422);

        $this->assertSame('simulado', $lote->fresh()->estado);
        $this->assertSame(0, ProductoCosto::where('producto_id', $producto->id)->count());
        $this->assertSame('SKU920002', $producto->fresh()->sku);
    }

    /**
     * @param  list<string>  $permisos
     */
    private function usuario(array $permisos): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permisos);

        return $user;
    }

    private function almacen(Sucursal $sucursal): Almacen
    {
        return Almacen::create([
            'sucursal_id' => $sucursal->id,
            'codigo' => 'ALM-'.Str::lower(Str::random(5)),
            'nombre' => 'Almacén de prueba',
            'activo' => true,
        ]);
    }

    private function producto(int $folio): Producto
    {
        return Producto::create([
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'sku' => 'SKU'.$folio,
            'descripcion' => 'Producto '.$folio,
            'activo' => true,
        ]);
    }
}
