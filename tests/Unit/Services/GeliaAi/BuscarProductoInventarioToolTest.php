<?php

namespace Tests\Unit\Services\GeliaAi;

use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use App\Services\GeliaAi\Tools\BuscarProductoInventarioTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BuscarProductoInventarioToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_respeta_limit_y_no_incluye_costos_sin_flag(): void
    {
        Permission::findOrCreate('almacenes.productos.ver', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('almacenes.productos.ver');

        $almacen = Almacen::create([
            'codigo' => 'A1',
            'nombre' => 'Almacén 1',
            'activo' => true,
        ]);

        for ($i = 1; $i <= 8; $i++) {
            $producto = Producto::create([
                'uuid' => (string) Str::uuid(),
                'sku' => 'TEST'.$i,
                'descripcion' => 'Perfume prueba '.$i,
                'folio' => 900000 + $i,
                'codigo_barras' => '75000000000'.$i,
                'activo' => true,
            ]);
            Inventario::create([
                'producto_id' => $producto->id,
                'almacen_id' => $almacen->id,
                'existencia' => 10,
                'apartado' => 1,
            ]);
        }

        config([
            'gelia_ai.inventario_limit_default' => 3,
            'gelia_ai.inventario_limit_max' => 5,
        ]);

        $tool = app(BuscarProductoInventarioTool::class);
        $result = $tool->ejecutar($user, ['q' => 'Perfume prueba', 'limit' => 99]);

        $this->assertTrue($result['ok']);
        $this->assertLessThanOrEqual(5, $result['n']);
        $this->assertCount($result['n'], $result['items']);
        $this->assertFalse($result['con_precios'] ?? true);

        $json = json_encode($result);
        $this->assertStringNotContainsString('precio_venta', strtolower($json));
        $this->assertArrayHasKey('sku', $result['items'][0]);
        $this->assertArrayHasKey('s', $result['items'][0]);
        $this->assertArrayNotHasKey('p', $result['items'][0]);
    }

    public function test_con_precios_incluye_co_y_pv_si_hay_permiso(): void
    {
        Permission::findOrCreate('almacenes.productos.ver', 'web');
        Permission::findOrCreate('almacenes.costos.ver', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(['almacenes.productos.ver', 'almacenes.costos.ver']);

        $almacen = Almacen::create([
            'codigo' => 'VTA',
            'nombre' => 'Venta',
            'activo' => true,
        ]);
        $producto = Producto::create([
            'uuid' => (string) Str::uuid(),
            'sku' => 'PRC1',
            'descripcion' => 'Perfume precio test',
            'folio' => 880099,
            'codigo_barras' => '7509999999999',
            'activo' => true,
        ]);
        Inventario::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'existencia' => 5,
            'apartado' => 0,
        ]);
        \App\Models\ProductoCosto::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'costo' => 12.5,
            'precio_venta' => 99.9,
        ]);

        $result = app(BuscarProductoInventarioTool::class)->ejecutar($user, [
            'q' => 'Perfume precio test',
            'con_precios' => true,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['con_precios']);
        $this->assertSame(12.5, $result['items'][0]['p'][0]['co']);
        $this->assertSame(99.9, $result['items'][0]['p'][0]['pv']);
        $this->assertStringNotContainsString('precio_venta', strtolower(json_encode($result)));
    }

    public function test_sin_permiso_falla(): void
    {
        $user = User::factory()->create();
        $tool = app(BuscarProductoInventarioTool::class);
        $result = $tool->ejecutar($user, ['q' => 'x']);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('permiso', strtolower($result['error']));
    }

    public function test_encuentra_por_nombre_parcial_no_contiguo(): void
    {
        Permission::findOrCreate('almacenes.productos.ver', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('almacenes.productos.ver');

        $almacen = Almacen::create([
            'codigo' => 'VTA',
            'nombre' => 'Venta',
            'activo' => true,
        ]);

        $producto = Producto::create([
            'uuid' => (string) Str::uuid(),
            'sku' => '6294015149371',
            'descripcion' => 'C ARMAF ODYSSEY MANDARIN SKY 100ML. EDP.',
            'folio' => 880001,
            'codigo_barras' => '6294015149371',
            'activo' => true,
        ]);
        Inventario::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'existencia' => 280,
            'apartado' => 0,
        ]);

        $tool = app(BuscarProductoInventarioTool::class);
        $result = $tool->ejecutar($user, [
            'q' => 'Cuantos perfumes quedan del armaf mandarin Sky de 100 ml',
            'limit' => 3,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['n']);
        $this->assertFalse($result['exacto']);
        $this->assertTrue($result['sugerir']);
        $this->assertSame('6294015149371', $result['items'][0]['sku']);
        $this->assertSame(280.0, $result['items'][0]['s'][0]['d']);
    }

    public function test_limpia_ruido_conversacional_largo_y_encuentra_nombre(): void
    {
        Permission::findOrCreate('almacenes.productos.ver', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('almacenes.productos.ver');

        $almacen = Almacen::create([
            'codigo' => 'VTA',
            'nombre' => 'Venta',
            'activo' => true,
        ]);

        $producto = Producto::create([
            'uuid' => (string) Str::uuid(),
            'sku' => '810101501227',
            'descripcion' => 'D ARIANA G. MOD VANILLA 100ML. EDP',
            'folio' => 880002,
            'codigo_barras' => '810101501227',
            'activo' => true,
        ]);
        Inventario::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'existencia' => 1,
            'apartado' => 0,
        ]);

        $result = app(BuscarProductoInventarioTool::class)->ejecutar($user, [
            'q' => 'puedes darme la información del stock actual y los precios del Mod Vanilla?',
            'limit' => 3,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['n']);
        $this->assertSame('810101501227', $result['items'][0]['sku']);
    }
}
