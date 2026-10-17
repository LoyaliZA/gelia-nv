<?php

namespace Tests\Unit\Services\GeliaAi;

use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoCosto;
use App\Models\Sucursal;
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
        $user = $this->usuario(['almacenes.productos.ver']);
        $almacen = $this->almacenOperable($user, 'A1');

        for ($i = 1; $i <= 8; $i++) {
            $producto = $this->producto('TEST'.$i, 'Perfume prueba '.$i, 900000 + $i, '75000000000'.$i);
            Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'existencia' => 10, 'apartado' => 1]);
        }

        config(['gelia_ai.inventario_limit_default' => 3, 'gelia_ai.inventario_limit_max' => 5]);

        $result = app(BuscarProductoInventarioTool::class)->ejecutar($user, ['q' => 'Perfume prueba', 'limit' => 99]);

        $this->assertTrue($result['ok']);
        $this->assertSame(5, $result['n']);
        $this->assertTrue($result['hay_mas']);
        $this->assertArrayHasKey('aviso_paginacion', $result);
        $this->assertSame('parcial', $result['coincidencia']);
        $this->assertFalse($result['con_precios']);
        $this->assertStringNotContainsString('precio_venta', strtolower(json_encode($result)));
        $this->assertArrayNotHasKey('p', $result['items'][0]);

        $stock = $result['items'][0]['stock'];
        $this->assertSame(10.0, $stock['total']['e']);
        $this->assertSame(1.0, $stock['total']['ap']);
        $this->assertSame(9.0, $stock['total']['d']);
        $this->assertArrayHasKey('consultado_en', $result);
    }

    public function test_con_precios_incluye_co_y_pv_si_hay_permiso(): void
    {
        $user = $this->usuario(['almacenes.productos.ver', 'almacenes.costos.ver']);
        $almacen = $this->almacenOperable($user, 'VTA');
        $producto = $this->producto('PRC1', 'Perfume precio test', 880099, '7509999999999');
        Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'existencia' => 5, 'apartado' => 0]);
        ProductoCosto::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'costo' => 12.5, 'precio_venta' => 99.9]);

        $result = app(BuscarProductoInventarioTool::class)->ejecutar($user, ['q' => 'Perfume precio test', 'con_precios' => true]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['con_precios']);
        $this->assertSame(12.5, $result['items'][0]['p'][0]['co']);
        $this->assertSame(99.9, $result['items'][0]['p'][0]['pv']);
        $this->assertStringNotContainsString('precio_venta', strtolower(json_encode($result)));
    }

    public function test_sin_permiso_falla(): void
    {
        $result = app(BuscarProductoInventarioTool::class)->ejecutar(User::factory()->create(), ['q' => 'x']);

        $this->assertFalse($result['ok']);
        $this->assertSame('sin_permiso', $result['error_code']);
    }

    public function test_encuentra_por_nombre_parcial_no_contiguo(): void
    {
        $user = $this->usuario(['almacenes.productos.ver']);
        $almacen = $this->almacenOperable($user, 'VTA');
        $producto = $this->producto('6294015149371', 'C ARMAF ODYSSEY MANDARIN SKY 100ML. EDP.', 880001, '6294015149371');
        Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'existencia' => 280, 'apartado' => 0]);

        $result = app(BuscarProductoInventarioTool::class)->ejecutar($user, [
            'q' => 'Cuantos perfumes quedan del armaf mandarin Sky de 100 ml',
            'limit' => 3,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['n']);
        $this->assertSame('parcial', $result['coincidencia']);
        $this->assertFalse($result['requiere_eleccion']);
        $this->assertSame('6294015149371', $result['items'][0]['sku']);
        $this->assertSame(280.0, $result['items'][0]['stock']['filas'][0]['d']);
    }

    public function test_sku_exacto_se_marca_como_exacta(): void
    {
        $user = $this->usuario(['almacenes.productos.ver']);
        $almacen = $this->almacenOperable($user, 'VTA');
        $producto = $this->producto('810101501227', 'D ARIANA G. MOD VANILLA 100ML. EDP', 880002, '810101501227');
        Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'existencia' => 1, 'apartado' => 0]);

        $result = app(BuscarProductoInventarioTool::class)->ejecutar($user, ['q' => '810101501227']);

        $this->assertSame('exacta', $result['coincidencia']);
        $this->assertSame('810101501227', $result['items'][0]['sku']);
    }

    public function test_limpia_ruido_conversacional_largo_y_encuentra_nombre(): void
    {
        $user = $this->usuario(['almacenes.productos.ver']);
        $almacen = $this->almacenOperable($user, 'VTA');
        $producto = $this->producto('810101501227', 'D ARIANA G. MOD VANILLA 100ML. EDP', 880002, '810101501227');
        Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'existencia' => 1, 'apartado' => 0]);

        $result = app(BuscarProductoInventarioTool::class)->ejecutar($user, [
            'q' => 'puedes darme la información del stock actual y los precios del Mod Vanilla?',
            'limit' => 3,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['n']);
        $this->assertSame('810101501227', $result['items'][0]['sku']);
    }

    public function test_stock_solo_de_almacenes_en_alcance(): void
    {
        $user = $this->usuario(['almacenes.productos.ver', 'almacenes.costos.ver']);
        $propio = $this->almacenOperable($user, 'PROPIO');
        $ajeno = Almacen::create(['codigo' => 'AJENO', 'nombre' => 'Ajeno', 'activo' => true, 'sucursal_id' => Sucursal::factory()->create()->id]);
        $producto = $this->producto('SCOPE1', 'Perfume alcance', 770001, '7700000000001');
        Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $propio->id, 'existencia' => 3, 'apartado' => 0]);
        Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $ajeno->id, 'existencia' => 500, 'apartado' => 0]);
        ProductoCosto::create(['producto_id' => $producto->id, 'almacen_id' => $ajeno->id, 'costo' => 77, 'precio_venta' => 150]);

        $tool = app(BuscarProductoInventarioTool::class);
        $result = $tool->ejecutar($user, ['q' => 'SCOPE1', 'con_precios' => true]);

        $this->assertSame(1, $result['items'][0]['stock']['almacenes_con_registro']);
        $this->assertSame(3.0, $result['items'][0]['stock']['total']['e']);
        $this->assertSame(['PROPIO'], array_column($result['items'][0]['stock']['filas'], 'a'));
        $this->assertSame([], $result['items'][0]['p']);
        $this->assertStringNotContainsString('500', json_encode($result['items'][0]['stock']));

        $filtrado = $tool->ejecutar($user, ['q' => 'SCOPE1', 'almacen' => 'AJENO']);
        $this->assertFalse($filtrado['ok']);
        $this->assertSame('sin_permiso_almacen', $filtrado['error_code']);
    }

    public function test_sin_sucursales_operables_avisa_falta_de_permiso(): void
    {
        $user = $this->usuario(['almacenes.productos.ver']);
        $this->producto('NOSC1', 'Perfume sin alcance', 770002, '7700000000002');

        $result = app(BuscarProductoInventarioTool::class)->ejecutar($user, ['q' => 'NOSC1']);

        $this->assertTrue($result['ok']);
        $this->assertSame(0, $result['almacenes_en_alcance']);
        $this->assertArrayHasKey('aviso_alcance', $result);
    }

    public function test_ventas_requieren_permiso_de_reportes(): void
    {
        $user = $this->usuario(['almacenes.productos.ver']);
        $almacen = $this->almacenOperable($user, 'VTA');
        $producto = $this->producto('VEN1', 'Perfume ventas', 770003, '7700000000003');
        Inventario::create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'existencia' => 1, 'apartado' => 0]);

        $tool = app(BuscarProductoInventarioTool::class);
        $this->assertArrayNotHasKey('ven', $tool->ejecutar($user, ['q' => 'VEN1'])['items'][0]);

        Permission::findOrCreate('reportes.ventas.ver', 'web');
        $user->givePermissionTo('reportes.ventas.ver');
        $this->assertArrayHasKey('ven', $tool->ejecutar($user->fresh(), ['q' => 'VEN1'])['items'][0]);
    }

    /** @param  list<string>  $permisos */
    private function usuario(array $permisos): User
    {
        foreach ($permisos as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo($permisos);

        return $user;
    }

    private function almacenOperable(User $user, string $codigo): Almacen
    {
        $sucursal = Sucursal::factory()->create();
        $user->concederAccesoSucursal($sucursal, true);

        return Almacen::create(['codigo' => $codigo, 'nombre' => 'Almacén '.$codigo, 'activo' => true, 'sucursal_id' => $sucursal->id]);
    }

    private function producto(string $sku, string $descripcion, int $folio, string $barcode): Producto
    {
        return Producto::create([
            'uuid' => (string) Str::uuid(),
            'sku' => $sku,
            'descripcion' => $descripcion,
            'folio' => $folio,
            'codigo_barras' => $barcode,
            'activo' => true,
        ]);
    }
}
