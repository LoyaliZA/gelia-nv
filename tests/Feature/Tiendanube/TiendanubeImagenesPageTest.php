<?php

namespace Tests\Feature\Tiendanube;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use Tests\Support\RefreshDatabaseSafe;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TiendanubeImagenesPageTest extends TestCase
{
    use RefreshDatabaseSafe;

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

    public function test_busqueda_por_nombre_con_seo_nulo_y_distinto(): void
    {
        $user = $this->usuarioEditorImagenes();

        $seoNulo = TiendanubeProducto::create([
            'id' => 10,
            'name' => ['es' => 'Aromático Especial'],
            'seo_title' => null,
            'published' => true,
        ]);
        $nombreDistinto = TiendanubeProducto::create([
            'id' => 11,
            'name' => ['es' => 'Perfume Mandarina'],
            'seo_title' => 'SKU-XYZ',
            'published' => true,
        ]);
        $localePt = TiendanubeProducto::create([
            'id' => 12,
            'name' => ['pt' => 'Fragrância'],
            'seo_title' => 'otro',
            'published' => true,
        ]);
        TiendanubeProducto::create([
            'id' => 13,
            'name' => ['es' => 'Irrelevante'],
            'seo_title' => 'No coincide',
            'published' => true,
        ]);

        $this->actingAs($user)
            ->get(route('tiendanube.imagenes.index', ['search' => 'Aromático']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tiendanube/Imagenes', false)
                ->has('productos.data', 1)
                ->where('productos.data.0.id', $seoNulo->id)
            );

        $this->actingAs($user)
            ->get(route('tiendanube.imagenes.index', ['search' => 'Mandarina']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('productos.data', 1)
                ->where('productos.data.0.id', $nombreDistinto->id)
            );

        $this->actingAs($user)
            ->get(route('tiendanube.imagenes.index', ['search' => 'SKU-XYZ']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('productos.data', 1)
                ->where('productos.data.0.id', $nombreDistinto->id)
            );

        $this->actingAs($user)
            ->get(route('tiendanube.imagenes.index', ['search' => 'Fragrância']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('productos.data', 1)
                ->where('productos.data.0.id', $localePt->id)
            );
    }

    public function test_busqueda_por_sku_y_no_por_tags_en_imagenes(): void
    {
        $user = $this->usuarioEditorImagenes();

        $conSku = TiendanubeProducto::create([
            'id' => 20,
            'name' => ['es' => 'Con SKU'],
            'tags' => 'etiqueta-unica',
            'published' => true,
        ]);
        TiendanubeProductoVariante::create([
            'id' => 200,
            'producto_id' => 20,
            'sku' => 'GEL-SKU-99',
            'price' => 10,
        ]);
        TiendanubeProducto::create([
            'id' => 21,
            'name' => ['es' => 'Solo tags'],
            'tags' => 'etiqueta-unica',
            'published' => true,
        ]);

        $this->actingAs($user)
            ->get(route('tiendanube.imagenes.index', ['search' => 'GEL-SKU-99']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('productos.data', 1)
                ->where('productos.data.0.id', $conSku->id)
            );

        $this->actingAs($user)
            ->get(route('tiendanube.imagenes.index', ['search' => 'etiqueta-unica']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('productos.data', 0));
    }

    public function test_busqueda_no_amplia_fuera_de_filtros_de_imagen(): void
    {
        $user = $this->usuarioEditorImagenes();

        $sinImg = TiendanubeProducto::create([
            'id' => 30,
            'name' => ['es' => 'Aromático Sin Foto'],
            'published' => true,
        ]);
        $conAlerta = TiendanubeProducto::create([
            'id' => 31,
            'name' => ['es' => 'Aromático Alerta'],
            'published' => true,
        ]);
        $ok = TiendanubeProducto::create([
            'id' => 32,
            'name' => ['es' => 'Aromático Ok'],
            'published' => true,
        ]);

        TiendanubeProductoImagen::create([
            'id' => 310,
            'producto_id' => $conAlerta->id,
            'src' => 'https://cdn.example.com/a.webp',
            'position' => 1,
            'requiere_revision' => true,
            'alerta_pequena' => false,
            'alerta_no_cuadrada' => true,
        ]);
        TiendanubeProductoImagen::create([
            'id' => 320,
            'producto_id' => $ok->id,
            'src' => 'https://cdn.example.com/b.webp',
            'position' => 1,
            'requiere_revision' => false,
            'alerta_pequena' => false,
            'alerta_no_cuadrada' => false,
        ]);

        $this->actingAs($user)
            ->get(route('tiendanube.imagenes.index', [
                'search' => 'Aromático',
                'sin_imagen' => 1,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('productos.data', 1)
                ->where('productos.data.0.id', $sinImg->id)
            );

        $this->actingAs($user)
            ->get(route('tiendanube.imagenes.index', [
                'search' => 'Aromático',
                'imagenes_alerta' => 1,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('productos.data', 1)
                ->where('productos.data.0.id', $conAlerta->id)
            );
    }

    private function usuarioEditorImagenes(): User
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.productos.editar', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(['tiendanube.ver', 'tiendanube.productos.editar']);

        return $user;
    }
}
