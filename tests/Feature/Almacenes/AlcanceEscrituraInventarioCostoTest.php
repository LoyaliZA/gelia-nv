<?php

namespace Tests\Feature\Almacenes;

use App\Models\Almacen;
use App\Models\AuditoriaConfiguracion;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoCosto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class AlcanceEscrituraInventarioCostoTest extends TestCase
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
            'almacenes.costos.gestionar',
            'catalogos.gestionar',
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    public function test_alta_de_inventario_exige_almacen_operable_y_audita_solo_el_exito(): void
    {
        [$user, $propio, $ajeno] = $this->operadorInventarios();
        $producto = $this->producto();

        $this->actingAs($user)
            ->post(route('almacenes.inventarios.store'), [
                'producto_id' => $producto->id,
                'almacen_id' => $ajeno->id,
                'existencia' => 3,
            ])
            ->assertSessionHasErrors('almacen_id');

        $this->assertSame(0, Inventario::count());
        $this->assertSame(0, AuditoriaConfiguracion::count());

        $this->actingAs($user)
            ->post(route('almacenes.inventarios.store'), [
                'producto_id' => $producto->id,
                'almacen_id' => $propio->id,
                'existencia' => 3,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventarios', [
            'producto_id' => $producto->id,
            'almacen_id' => $propio->id,
        ]);
        $this->assertSame(1, AuditoriaConfiguracion::where('accion', 'Inventario modificado')->count());
    }

    public function test_editar_inventario_ajeno_hacia_almacen_propio_no_cambia_ni_audita(): void
    {
        [$user, $propio, $ajeno] = $this->operadorInventarios();
        $producto = $this->producto();
        $inventario = Inventario::create([
            'producto_id' => $producto->id,
            'almacen_id' => $ajeno->id,
            'existencia' => 5,
            'apartado' => 1,
            'ubicacion' => 'AJENA',
        ]);

        $this->actingAs($user)
            ->put(route('almacenes.inventarios.update', $inventario), [
                'producto_id' => $producto->id,
                'almacen_id' => $propio->id,
                'existencia' => 9,
                'ubicacion' => 'PROPIA',
            ])
            ->assertSessionHasErrors('almacen_id');

        $inventario->refresh();
        $this->assertSame($ajeno->id, $inventario->almacen_id);
        $this->assertSame('5.000', $inventario->existencia);
        $this->assertSame('AJENA', $inventario->ubicacion);
        $this->assertSame(0, AuditoriaConfiguracion::count());
    }

    public function test_editar_y_borrar_inventario_dentro_del_alcance_audita_el_exito(): void
    {
        [$user, $propio] = $this->operadorInventarios();
        $producto = $this->producto();
        $inventario = Inventario::create([
            'producto_id' => $producto->id,
            'almacen_id' => $propio->id,
            'existencia' => 2,
            'apartado' => 0,
        ]);

        $this->actingAs($user)
            ->put(route('almacenes.inventarios.update', $inventario), [
                'producto_id' => $producto->id,
                'almacen_id' => $propio->id,
                'existencia' => 6,
            ])
            ->assertRedirect();

        $this->assertSame('6.000', $inventario->fresh()->existencia);
        $this->assertSame(1, AuditoriaConfiguracion::where('accion', 'Inventario modificado')->count());

        $this->actingAs($user)
            ->delete(route('almacenes.inventarios.destroy', $inventario))
            ->assertRedirect();

        $this->assertNull(Inventario::find($inventario->id));
        $this->assertSame(1, AuditoriaConfiguracion::where('accion', 'Inventario eliminado')->count());
    }

    public function test_borrar_inventario_fuera_de_alcance_conserva_el_registro(): void
    {
        [$user, , $ajeno] = $this->operadorInventarios();
        $inventario = Inventario::create([
            'producto_id' => $this->producto()->id,
            'almacen_id' => $ajeno->id,
            'existencia' => 4,
        ]);

        $this->actingAs($user)
            ->delete(route('almacenes.inventarios.destroy', $inventario))
            ->assertForbidden();

        $this->assertNotNull(Inventario::find($inventario->id));
        $this->assertSame(0, AuditoriaConfiguracion::count());
    }

    public function test_catalogos_no_crea_inventario_y_solo_borra_dentro_del_alcance(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('catalogos.gestionar');
        $sucursal = Sucursal::factory()->create();
        $user->concederAccesoSucursal($sucursal, esPrincipal: true);
        $propio = $this->almacen($sucursal, 'CAT');
        $ajeno = $this->almacen(Sucursal::factory()->create(), 'OTRO');
        $producto = $this->producto();
        $dentro = Inventario::create([
            'producto_id' => $producto->id,
            'almacen_id' => $propio->id,
            'existencia' => 1,
        ]);
        $fuera = Inventario::create([
            'producto_id' => $this->producto()->id,
            'almacen_id' => $ajeno->id,
            'existencia' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('almacenes.inventarios.store'), [
                'producto_id' => $producto->id,
                'almacen_id' => $propio->id,
                'existencia' => 2,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('almacenes.inventarios.update', $dentro), [
                'producto_id' => $producto->id,
                'almacen_id' => $propio->id,
                'existencia' => 8,
            ])
            ->assertForbidden();

        $this->assertSame('1.000', $dentro->fresh()->existencia);

        $this->actingAs($user)
            ->delete(route('almacenes.inventarios.destroy', $fuera))
            ->assertForbidden();
        $this->assertNotNull(Inventario::find($fuera->id));

        $this->actingAs($user)
            ->delete(route('almacenes.inventarios.destroy', $dentro))
            ->assertRedirect();

        $this->assertNull(Inventario::find($dentro->id));
        $this->assertSame(1, AuditoriaConfiguracion::where('accion', 'Inventario eliminado')->count());
    }

    public function test_super_admin_sin_la_sucursal_no_borra_el_inventario_ajeno(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Super Admin', 'web'));
        $propia = Sucursal::factory()->create();
        $user->concederAccesoSucursal($propia, esPrincipal: true);
        $ajeno = $this->almacen(Sucursal::factory()->create(), 'SA');
        $inventario = Inventario::create([
            'producto_id' => $this->producto()->id,
            'almacen_id' => $ajeno->id,
            'existencia' => 7,
        ]);

        $this->actingAs($user)
            ->delete(route('almacenes.inventarios.destroy', $inventario))
            ->assertForbidden();

        $this->assertSame('7.000', $inventario->fresh()->existencia);
        $this->assertSame(0, AuditoriaConfiguracion::count());
    }

    public function test_editar_costo_ajeno_hacia_almacen_propio_no_cambia_ni_audita(): void
    {
        [$user, $propio, $ajeno] = $this->operadorCostos();
        $producto = $this->producto();
        $costo = ProductoCosto::create([
            'producto_id' => $producto->id,
            'almacen_id' => $ajeno->id,
            'costo' => 4,
            'precio_venta' => 9,
        ]);

        $this->actingAs($user)
            ->put(route('almacenes.costos.update', $costo), [
                'producto_id' => $producto->id,
                'almacen_id' => $propio->id,
                'costo' => 15,
                'precio_venta' => 30,
            ])
            ->assertSessionHasErrors('almacen_id');

        $costo->refresh();
        $this->assertSame($ajeno->id, $costo->almacen_id);
        $this->assertSame('4.00', $costo->costo);
        $this->assertSame('9.00', $costo->precio_venta);
        $this->assertSame(0, AuditoriaConfiguracion::count());
    }

    public function test_borrar_costo_fuera_de_alcance_conserva_el_registro_y_dentro_audita(): void
    {
        [$user, $propio, $ajeno] = $this->operadorCostos();
        $fuera = ProductoCosto::create([
            'producto_id' => $this->producto()->id,
            'almacen_id' => $ajeno->id,
            'costo' => 2,
        ]);
        $dentro = ProductoCosto::create([
            'producto_id' => $this->producto()->id,
            'almacen_id' => $propio->id,
            'costo' => 3,
        ]);

        $this->actingAs($user)
            ->delete(route('almacenes.costos.destroy', $fuera))
            ->assertForbidden();
        $this->assertNotNull(ProductoCosto::find($fuera->id));
        $this->assertSame(0, AuditoriaConfiguracion::count());

        $this->actingAs($user)
            ->delete(route('almacenes.costos.destroy', $dentro))
            ->assertRedirect();
        $this->assertNull(ProductoCosto::find($dentro->id));
        $this->assertSame(1, AuditoriaConfiguracion::where('accion', 'Costo eliminado')->count());
    }

    public function test_catalogos_no_crea_costo(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['catalogos.gestionar', 'almacenes.costos.ver']);
        $sucursal = Sucursal::factory()->create();
        $user->concederAccesoSucursal($sucursal, esPrincipal: true);
        $almacen = $this->almacen($sucursal, 'COST');

        $this->actingAs($user)
            ->post(route('almacenes.costos.store'), [
                'producto_id' => $this->producto()->id,
                'almacen_id' => $almacen->id,
                'costo' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(0, ProductoCosto::count());
        $this->assertSame(0, AuditoriaConfiguracion::count());
    }

    /**
     * @return array{0: User, 1: Almacen, 2: Almacen}
     */
    private function operadorInventarios(): array
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            'almacenes.inventarios.ver',
            'almacenes.inventarios.gestionar',
        ]);
        $sucursal = Sucursal::factory()->create();
        $user->concederAccesoSucursal($sucursal, esPrincipal: true);

        return [$user, $this->almacen($sucursal, 'INV'), $this->almacen(Sucursal::factory()->create(), 'INVX')];
    }

    /**
     * @return array{0: User, 1: Almacen, 2: Almacen}
     */
    private function operadorCostos(): array
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            'almacenes.costos.ver',
            'almacenes.costos.gestionar',
        ]);
        $sucursal = Sucursal::factory()->create();
        $user->concederAccesoSucursal($sucursal, esPrincipal: true);

        return [$user, $this->almacen($sucursal, 'CO'), $this->almacen(Sucursal::factory()->create(), 'COX')];
    }

    private function almacen(Sucursal $sucursal, string $codigo): Almacen
    {
        return Almacen::create([
            'sucursal_id' => $sucursal->id,
            'codigo' => 'ALM-'.$codigo.'-'.Str::lower(Str::random(4)),
            'nombre' => 'Almacén '.$codigo,
            'activo' => true,
        ]);
    }

    private function producto(): Producto
    {
        static $folio = 910000;
        $folio++;

        return Producto::create([
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'sku' => 'SKU'.$folio,
            'descripcion' => 'Producto '.$folio,
            'activo' => true,
        ]);
    }
}
