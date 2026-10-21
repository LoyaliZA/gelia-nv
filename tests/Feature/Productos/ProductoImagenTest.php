<?php

namespace Tests\Feature\Productos;

use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\ProductoImagenLimpieza;
use App\Models\User;
use App\Services\Productos\Imagenes\AlmacenArchivoImagenProducto;
use App\Services\Productos\Imagenes\ValidarArchivoImagenProducto;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class ProductoImagenTest extends TestCase
{
    use RefreshDatabaseSafe;

    private string $raiz = '';

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
        $this->prepararDisco();
        foreach ([
            'gestion_interna.productos.ver',
            'gestion_interna.productos.gestionar',
            'almacenes.productos.ver',
            'almacenes.productos.gestionar',
            'almacenes.inventarios.ver',
            'catalogos.gestionar',
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    public function test_consulta_lee_y_gestion_escribe_sin_tocar_el_maestro(): void
    {
        $producto = $this->producto();
        $marca = [
            'updated_at' => $producto->updated_at?->toJSON(),
            'folio' => $producto->folio,
            'sku' => $producto->sku,
            'imagen_path' => $producto->imagen_path,
        ];
        $lector = $this->usuario(['gestion_interna.productos.ver']);
        $gestor = $this->usuario(['gestion_interna.productos.ver', 'gestion_interna.productos.gestionar']);

        $this->actingAs($lector)
            ->post(route('gestion_interna.productos.imagenes.store', $producto), ['archivo' => $this->png()], $this->aceptaJson())
            ->assertForbidden();
        $this->assertSame(0, ProductoImagen::count());

        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.store', $producto), ['archivo' => $this->png()], $this->aceptaJson())
            ->assertOk();

        $imagen = ProductoImagen::query()->firstOrFail();
        $this->assertSame($producto->id, $imagen->principal_producto_id);
        $this->assertTrue(str_starts_with($imagen->clave_original, 'productos/imagenes/'.$producto->id.'/'));
        $this->assertStringNotContainsString('foto.png', $imagen->clave_original);
        Storage::disk('imagenes_prueba')->assertExists($imagen->clave_original);

        $this->actingAs($lector)
            ->get(route('gestion_interna.productos.imagenes.archivo', [$producto, $imagen, 'variante' => 'original']))
            ->assertOk();

        $otro = $this->producto();
        $this->actingAs($lector)
            ->get(route('gestion_interna.productos.imagenes.archivo', [$otro, $imagen]))
            ->assertNotFound();

        $fresco = $producto->fresh();
        $this->assertSame($marca['updated_at'], $fresco->updated_at?->toJSON());
        $this->assertSame($marca['folio'], $fresco->folio);
        $this->assertSame($marca['sku'], $fresco->sku);
        $this->assertNull($fresco->imagen_path);

        $this->actingAs($this->usuario(['almacenes.inventarios.ver']))
            ->get(route('gestion_interna.productos.imagenes.archivo', [$producto, $imagen]))
            ->assertForbidden();
        $this->actingAs($this->usuario(['almacenes.productos.ver', 'almacenes.productos.gestionar']))
            ->post(route('gestion_interna.productos.imagenes.store', $producto), ['archivo' => $this->png()], $this->aceptaJson())
            ->assertForbidden();
    }

    public function test_rechaza_archivo_invalido_y_exceso_de_pixeles_antes_de_crear_fila(): void
    {
        $producto = $this->producto();
        $gestor = $this->usuario(['gestion_interna.productos.ver', 'gestion_interna.productos.gestionar']);
        $this->assertTrue(app(ValidarArchivoImagenProducto::class)->superaLimitePixeles(8000, 8000));

        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.store', $producto), [
                'archivo' => $this->texto(),
            ], $this->aceptaJson())
            ->assertStatus(422);
        $this->assertSame(0, ProductoImagen::count());

        config(['productos_imagenes.max_pixeles' => 10]);
        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.store', $producto), ['archivo' => $this->png(20, 20)], $this->aceptaJson())
            ->assertStatus(422);
        $this->assertSame(0, ProductoImagen::count());
    }

    public function test_principal_orden_reemplazo_y_desvinculacion(): void
    {
        $producto = $this->producto();
        $gestor = $this->usuario(['gestion_interna.productos.ver', 'gestion_interna.productos.gestionar']);
        $this->app->instance(AlmacenArchivoImagenProducto::class, new class extends AlmacenArchivoImagenProducto
        {
            public function eliminarSiLibre(string $clave): void
            {
                throw new \RuntimeException('fallo de borrado');
            }
        });
        $this->actingAs($gestor)->post(route('gestion_interna.productos.imagenes.store', $producto), ['archivo' => $this->png()], $this->aceptaJson())->assertOk();
        $this->actingAs($gestor)->post(route('gestion_interna.productos.imagenes.store', $producto), ['archivo' => $this->png()], $this->aceptaJson())->assertOk();
        $imagenes = ProductoImagen::query()->orderBy('posicion')->get();
        $primera = $imagenes[0];
        $segunda = $imagenes[1];
        $this->assertSame($producto->id, $primera->principal_producto_id);
        $this->assertNull($segunda->principal_producto_id);

        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.principal', [$producto, $segunda]), [], $this->aceptaJson())
            ->assertOk();
        $this->assertNull($primera->fresh()->principal_producto_id);
        $this->assertSame($producto->id, $segunda->fresh()->principal_producto_id);

        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.orden', $producto), [
                'orden' => [$segunda->id, $primera->id],
            ], $this->aceptaJson())
            ->assertOk();
        $this->assertSame(1, $segunda->fresh()->posicion);
        $this->assertSame(2, $primera->fresh()->posicion);

        $claveAnterior = $segunda->fresh()->clave_original;
        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.reemplazar', [$producto, $segunda]), [
                'archivo' => $this->texto(),
            ], $this->aceptaJson())
            ->assertStatus(422);
        $this->assertSame($claveAnterior, $segunda->fresh()->clave_original);
        Storage::disk('imagenes_prueba')->assertExists($claveAnterior);

        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.reemplazar', [$producto, $segunda]), ['archivo' => $this->png()], $this->aceptaJson())
            ->assertOk();
        $reemplazada = $segunda->fresh();
        $this->assertNotSame($claveAnterior, $reemplazada->clave_original);
        Storage::disk('imagenes_prueba')->assertExists($claveAnterior);
        Storage::disk('imagenes_prueba')->assertExists($reemplazada->clave_original);
        $this->assertTrue(ProductoImagenLimpieza::query()->where('clave', $claveAnterior)->exists());
        $this->assertSame($producto->updated_at?->toJSON(), $producto->fresh()->updated_at?->toJSON());

        $this->actingAs($gestor)
            ->delete(route('gestion_interna.productos.imagenes.destruir', [$producto, $reemplazada]), [], $this->aceptaJson())
            ->assertOk();
        $this->assertNull(ProductoImagen::query()->find($reemplazada->id));
        $this->assertSame($producto->id, $primera->fresh()->principal_producto_id);
        $this->assertNotNull($producto->fresh());
    }

    public function test_el_listado_muestra_miniatura_y_filtra_sin_imagen(): void
    {
        $conImagen = $this->producto(['descripcion' => 'Rosa unica 771']);
        $sinImagen = $this->producto(['descripcion' => 'Lirio aparte 882']);
        $gestor = $this->usuario(['gestion_interna.productos.ver', 'gestion_interna.productos.gestionar']);
        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.store', $conImagen), ['archivo' => $this->png()], $this->aceptaJson())
            ->assertOk();

        $this->actingAs($gestor)
            ->get(route('gestion_interna.productos.index', ['sin_imagen' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('productos.data', 1)
                ->where('productos.data.0.id', $sinImagen->id)
            );

        $this->actingAs($gestor)
            ->get(route('gestion_interna.productos.index', ['q' => 'Rosa unica 771']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('productos.data.0.miniatura_url', fn ($url) => is_string($url) && str_contains($url, '/imagenes/'))
            );
    }

    private function usuario(array $permisos): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permisos);

        return $user;
    }

    private function producto(array $extra = []): Producto
    {
        static $folio = 920000;
        $folio++;

        return Producto::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'sku' => 'SKU'.$folio,
            'descripcion' => 'Producto '.$folio,
            'activo' => true,
        ], $extra));
    }

    private function prepararDisco(): void
    {
        $this->raiz = sys_get_temp_dir().'/gelia-img-'.bin2hex(random_bytes(4));
        mkdir($this->raiz, 0700, true);
        config([
            'filesystems.disks.imagenes_prueba' => [
                'driver' => 'local',
                'root' => $this->raiz,
                'throw' => false,
            ],
            'productos_imagenes.disk' => 'imagenes_prueba',
        ]);
    }

    private function texto(): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($ruta, 'no es imagen');

        return new UploadedFile($ruta, 'nota.txt', 'text/plain', \UPLOAD_ERR_OK, true);
    }

    private function png(int $ancho = 8, int $alto = 8): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'png');
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagepng($imagen, $ruta);
        imagedestroy($imagen);

        return new UploadedFile($ruta, 'foto.png', 'image/png', \UPLOAD_ERR_OK, true);
    }

    /**
     * @return array<string, string>
     */
    private function aceptaJson(): array
    {
        return ['Accept' => 'application/json'];
    }
}
