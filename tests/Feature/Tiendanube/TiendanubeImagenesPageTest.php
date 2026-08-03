<?php

namespace Tests\Feature\Tiendanube;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TiendanubeImagenesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => 8004291,
            'app_id' => '37163',
            'access_token' => Crypt::encryptString('token-test'),
        ])->save();
    }

    public function test_imagenes_index_requiere_permiso_editar(): void
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.productos.editar', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('tiendanube.ver');

        $this->actingAs($user)
            ->get(route('tiendanube.imagenes.index'))
            ->assertForbidden();

        $user->givePermissionTo('tiendanube.productos.editar');

        $this->actingAs($user)
            ->get(route('tiendanube.imagenes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tiendanube/Imagenes', false)
                ->has('totales.productos')
                ->has('totales.sin_imagen')
                ->has('totales.productos_alerta_imagenes')
            );
    }

    public function test_filtro_sin_imagen_y_alerta(): void
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.productos.editar', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(['tiendanube.ver', 'tiendanube.productos.editar']);

        $sinImg = TiendanubeProducto::create(['id' => 1, 'name' => ['es' => 'Sin'], 'published' => true]);
        $conAlerta = TiendanubeProducto::create(['id' => 2, 'name' => ['es' => 'Alerta'], 'published' => true]);
        $ok = TiendanubeProducto::create(['id' => 3, 'name' => ['es' => 'Ok'], 'published' => true]);

        TiendanubeProductoImagen::create([
            'id' => 10,
            'producto_id' => $conAlerta->id,
            'src' => 'https://cdn.example.com/a.webp',
            'position' => 1,
            'width' => 900,
            'height' => 1600,
            'requiere_revision' => true,
            'alerta_pequena' => false,
            'alerta_no_cuadrada' => true,
        ]);
        TiendanubeProductoImagen::create([
            'id' => 11,
            'producto_id' => $ok->id,
            'src' => 'https://cdn.example.com/b.webp',
            'position' => 1,
            'width' => 1280,
            'height' => 1280,
            'requiere_revision' => false,
            'alerta_pequena' => false,
            'alerta_no_cuadrada' => false,
        ]);

        $this->actingAs($user)
            ->get(route('tiendanube.imagenes.index', ['sin_imagen' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tiendanube/Imagenes', false)
                ->where('filters.sin_imagen', true)
                ->has('productos.data', 1)
                ->where('productos.data.0.id', $sinImg->id)
                ->where('totales.sin_imagen', 1)
            );

        $this->actingAs($user)
            ->get(route('tiendanube.imagenes.index', ['imagenes_alerta' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tiendanube/Imagenes', false)
                ->where('filters.imagenes_alerta', true)
                ->has('productos.data', 1)
                ->where('productos.data.0.id', $conAlerta->id)
                ->where('totales.productos_alerta_imagenes', 1)
            );
    }
}
