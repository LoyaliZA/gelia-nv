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

    public function test_respeta_limit_y_no_incluye_costos(): void
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

        $json = json_encode($result);
        $this->assertStringNotContainsString('costo', strtolower($json));
        $this->assertStringNotContainsString('precio_venta', strtolower($json));
        $this->assertArrayHasKey('sku', $result['items'][0]);
        $this->assertArrayHasKey('s', $result['items'][0]);
    }

    public function test_sin_permiso_falla(): void
    {
        $user = User::factory()->create();
        $tool = app(BuscarProductoInventarioTool::class);
        $result = $tool->ejecutar($user, ['q' => 'x']);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('permiso', strtolower($result['error']));
    }
}
