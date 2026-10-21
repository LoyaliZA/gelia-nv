<?php

namespace Tests\Feature\Almacenes;

use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\ProductoCosto;
use App\Models\ProductoVentaAlmacen;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Productos\ArmarFichaProductoService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class FichaPresenciaProductoTest extends TestCase
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
            'gestion_interna.productos.ver',
            'gestion_interna.productos.gestionar',
            'almacenes.productos.ver',
            'almacenes.inventarios.ver',
            'almacenes.costos.ver',
            'catalogos.gestionar',
            'reportes.ventas.ver',
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    public function test_la_ficha_actual_conserva_stock_y_los_datos_maestros_no_consultan_inventario(): void
    {
        $user = $this->usuario(['gestion_interna.productos.ver']);
        $sucursal = Sucursal::factory()->create();
        $user->concederAccesoSucursal($sucursal, esPrincipal: true);
        $propia = $this->almacen($sucursal, 'PROPIA');
        $ajena = $this->almacen(Sucursal::factory()->create(), 'AJENA');
        $producto = $this->producto();
        Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $propia->id, 'existencia' => null, 'apartado' => 0]);
        Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $ajena->id, 'existencia' => 4, 'apartado' => 0]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $maestro = app(ArmarFichaProductoService::class)->datosMaestros($producto);
        $consultas = collect(DB::getQueryLog())->pluck('query')->implode(' ');
        $this->assertArrayNotHasKey('stock', $maestro);
        $this->assertArrayNotHasKey('ventas', $maestro);
        $this->assertStringNotContainsString('inventarios', $consultas);
        $this->assertStringNotContainsString('producto_ventas_almacen', $consultas);

        $this->actingAs($user)
            ->getJson(route('gestion_interna.productos.ficha', $producto))
            ->assertOk()
            ->assertJsonPath('ficha.sku', $producto->sku)
            ->assertJsonCount(2, 'ficha.stock')
            ->assertJsonPath('ficha.stock.0.e', fn ($valor) => $valor === 0 || $valor === 0.0 || $valor === 4 || $valor === 4.0);
    }

    public function test_presencia_pagina_alcance_estados_y_rechaza_contexto_invalido(): void
    {
        $user = $this->usuario(['gestion_interna.productos.ver']);
        $sucursal = Sucursal::factory()->create();
        $user->concederAccesoSucursal($sucursal, esPrincipal: true);
        $producto = $this->producto();
        $almacenes = [];
        for ($i = 1; $i <= 6; $i++) {
            $almacenes[] = $this->almacen($sucursal, 'A'.$i);
        }
        ProductoAlmacen::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacenes[0]->id,
            'ubicacion' => 'B-1',
            'activo_en_almacen' => true,
            'origen_asignacion' => 'manual',
        ]);
        Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $almacenes[1]->id, 'existencia' => null, 'apartado' => 0]);
        Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $almacenes[2]->id, 'existencia' => 0, 'apartado' => 0]);
        $ajena = $this->almacen(Sucursal::factory()->create(), 'FUERA');
        Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $ajena->id, 'existencia' => 9, 'apartado' => 0]);
        $inactivo = Almacen::create([
            'sucursal_id' => $sucursal->id,
            'codigo' => 'ALM-INACTIVO',
            'nombre' => 'Inactivo',
            'activo' => false,
        ]);
        ProductoAlmacen::create([
            'producto_id' => $producto->id,
            'almacen_id' => $inactivo->id,
            'activo_en_almacen' => true,
            'origen_asignacion' => 'manual',
        ]);

        $this->actingAs($user)
            ->get(route('almacenes.productos.ficha', [
                'producto' => $producto,
                'per_page' => 2,
                'presencia_page' => 1,
                'almacen_ids' => [$ajena->id],
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('presencia.total', 3)
                ->has('presencia.data', 2)
                ->where('incluye_costos', false)
                ->where('incluye_ventas', false)
                ->missing('presencia.data.0.costo')
                ->missing('presencia.data.0.ventas')
                ->where('presencia.data', fn ($filas) => collect($filas)->pluck('almacen_id')->contains($ajena->id) === false
                    && collect($filas)->pluck('almacen_id')->contains($inactivo->id) === false)
            );

        $this->actingAs($user)
            ->get(route('almacenes.productos.ficha', [
                'producto' => $producto,
                'per_page' => 20,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('presencia.total', 3)
                ->where('presencia.data', function ($filas) use ($almacenes) {
                    $porId = collect($filas)->keyBy('almacen_id');

                    return $porId[$almacenes[0]->id]['asignado'] === true
                        && $porId[$almacenes[0]->id]['estado_existencia'] === 'sin_registro'
                        && $porId[$almacenes[0]->id]['existencia'] === null
                        && $porId[$almacenes[1]->id]['estado_existencia'] === 'nula'
                        && $porId[$almacenes[1]->id]['asignado'] === false
                        && $porId[$almacenes[2]->id]['estado_existencia'] === 'cero'
                        && $porId[$almacenes[2]->id]['tiene_cantidad'] === true;
                })
            );

        $sucursalB = Sucursal::factory()->create();
        $user->concederAccesoSucursal($sucursalB);
        $almacenB = $this->almacen($sucursalB, 'B');
        $this->from('/almacenes')
            ->actingAs($user)
            ->get(route('almacenes.productos.ficha', [
                'producto' => $producto,
                'sucursal_id' => $sucursal->id,
                'almacen_id' => $almacenB->id,
            ]))
            ->assertSessionHasErrors('almacen_id');
        $this->actingAs($user)
            ->get(route('almacenes.productos.ficha', [
                'producto' => $producto,
                'almacen_id' => $ajena->id,
            ]))
            ->assertForbidden();

        $otra = $this->almacen($sucursal, 'OTRA');
        $this->from('/almacenes')
            ->actingAs($user)
            ->get(route('almacenes.productos.ficha', [
                'producto' => $producto,
                'desde' => 'https://ejemplo.invalid/robo',
                'sucursal_id' => $sucursal->id,
                'almacen_id' => $otra->id,
            ]))
            ->assertSessionHasErrors('desde');

        $this->actingAs($this->usuario(['almacenes.inventarios.ver']))
            ->get(route('almacenes.productos.ficha', $producto))
            ->assertForbidden();
    }

    public function test_costos_y_ventas_solo_entran_con_su_permiso(): void
    {
        $sucursal = Sucursal::factory()->create();
        $almacen = $this->almacen($sucursal, 'COSTO');
        $producto = $this->producto();
        ProductoCosto::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'costo' => 12.5,
            'costo_reposicion' => 10,
            'precio_venta' => 30,
        ]);
        ProductoVentaAlmacen::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'periodo' => '2026-10',
            'cantidad_vendida' => 2,
            'monto_venta' => 60,
        ]);

        $conDatos = $this->usuario(['gestion_interna.productos.ver', 'almacenes.costos.ver', 'reportes.ventas.ver']);
        $conDatos->concederAccesoSucursal($sucursal, esPrincipal: true);
        $this->actingAs($conDatos)
            ->get(route('almacenes.productos.ficha', $producto))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('incluye_costos', true)
                ->where('incluye_ventas', true)
                ->where('presencia.data.0.precio_venta', '30.00')
                ->where('presencia.data.0.ventas.0.periodo', '2026-10')
            );
    }

    private function usuario(array $permisos): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permisos);

        return $user;
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
            'imagen_path' => null,
        ]);
    }
}
