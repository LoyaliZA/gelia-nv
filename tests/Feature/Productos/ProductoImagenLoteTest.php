<?php

namespace Tests\Feature\Productos;

use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\ProductoImagenLote;
use App\Models\ProductoImagenLoteArchivo;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class ProductoImagenLoteTest extends TestCase
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
        $raiz = sys_get_temp_dir().'/gelia-lote-'.bin2hex(random_bytes(4));
        mkdir($raiz, 0700, true);
        config([
            'filesystems.disks.imagenes_prueba' => [
                'driver' => 'local',
                'root' => $raiz,
                'throw' => false,
            ],
            'productos_imagenes.disk' => 'imagenes_prueba',
        ]);
        foreach ([
            'gestion_interna.productos.ver',
            'gestion_interna.productos.gestionar',
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    public function test_lote_parcial_ambiguo_repetido_y_reintento_sin_duplicar(): void
    {
        $uno = $this->producto(930001, 'SKU-A');
        $dos = $this->producto(930002, 'SKU-B');
        $unico = $this->producto(930003, 'SKU-C');
        $gestor = $this->usuario();
        $nombreUnico = $unico->folio.'-ok.png';
        $nombreAmbiguo = $uno->folio.'-y-'.$dos->folio.'.png';
        $nombreRepite = $uno->folio.'-repite.png';
        $analisis = $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.lotes.analizar'), [
                'archivos' => [
                    $this->png($nombreUnico),
                    $this->png('sin-folio.png'),
                    $this->png($nombreAmbiguo),
                    $this->png($nombreRepite),
                    $this->png($uno->folio.'-otra.png'),
                    $this->texto('nota.txt'),
                ],
            ], $this->aceptaJson())
            ->assertOk()
            ->json('lote');

        $porNombre = collect($analisis['archivos'])->keyBy('nombre_original');
        $this->assertSame('listo', $porNombre[$nombreUnico]['estado']);
        $this->assertSame('unico', $porNombre[$nombreUnico]['clasificacion']);
        $this->assertSame('repetido', $porNombre[$nombreRepite]['clasificacion']);
        $this->assertSame('ambiguo', $porNombre[$nombreAmbiguo]['clasificacion']);
        $this->assertSame('sin_folio', $porNombre['sin-folio.png']['clasificacion']);
        $this->assertSame('invalido', $porNombre['nota.txt']['clasificacion']);
        $this->assertSame(0, ProductoImagen::count());

        $confirmacion = $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.lotes.confirmar', $analisis['uuid']), [
                'archivos' => [
                    ['id' => $porNombre[$nombreUnico]['id'], 'accion' => 'confirmar'],
                    ['id' => $porNombre['sin-folio.png']['id'], 'accion' => 'confirmar', 'producto_id' => $dos->id],
                    ['id' => $porNombre[$nombreAmbiguo]['id'], 'accion' => 'confirmar'],
                    ['id' => $porNombre[$nombreRepite]['id'], 'accion' => 'confirmar'],
                    ['id' => $porNombre['nota.txt']['id'], 'accion' => 'confirmar'],
                ],
            ], $this->aceptaJson())
            ->assertOk()
            ->json('archivos');

        $resultado = collect($confirmacion)->keyBy('id');
        $this->assertSame('completado', $resultado[$porNombre[$nombreUnico]['id']]['estado']);
        $this->assertSame('error', $resultado[$porNombre['sin-folio.png']['id']]['estado']);
        $this->assertSame('error', $resultado[$porNombre[$nombreRepite]['id']]['estado']);
        $this->assertSame(1, ProductoImagen::query()->where('producto_id', $unico->id)->count());
        $this->assertSame(0, ProductoImagen::query()->where('producto_id', $dos->id)->count());

        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.lotes.confirmar', $analisis['uuid']), [
                'archivos' => [
                    ['id' => $porNombre[$nombreUnico]['id'], 'accion' => 'confirmar'],
                    [
                        'id' => $porNombre[$nombreAmbiguo]['id'],
                        'accion' => 'confirmar',
                        'producto_id' => $dos->id,
                    ],
                    [
                        'id' => $porNombre[$nombreRepite]['id'],
                        'accion' => 'confirmar',
                        'producto_id' => $uno->id,
                        'confirmar_repetido' => true,
                    ],
                ],
            ], $this->aceptaJson())
            ->assertOk();

        $this->assertSame(1, ProductoImagen::query()->where('lote_archivo_id', $porNombre[$nombreUnico]['id'])->count());
        $this->assertSame(1, ProductoImagen::query()->where('producto_id', $dos->id)->count());
        $this->assertSame(1, ProductoImagen::query()->where('producto_id', $uno->id)->count());
        $this->assertSame($uno->sku, $uno->fresh()->sku);
        $this->assertNull($uno->fresh()->imagen_path);
    }

    public function test_un_reclamo_vigente_no_crea_otra_imagen_y_el_intento_caduca(): void
    {
        $producto = $this->producto(930010, 'SKU-C');
        $gestor = $this->usuario();
        $lote = $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.lotes.analizar'), [
                'archivos' => [$this->png($producto->folio.'.png')],
            ], $this->aceptaJson())
            ->json('lote');
        $archivo = ProductoImagenLoteArchivo::query()->findOrFail($lote['archivos'][0]['id']);
        $archivo->update(['estado' => 'reclamado', 'reclamado_en' => now()]);

        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.lotes.confirmar', $lote['uuid']), [
                'archivos' => [['id' => $archivo->id, 'accion' => 'confirmar']],
            ], $this->aceptaJson())
            ->assertOk()
            ->assertJsonPath('archivos.0.estado', 'error')
            ->assertJsonPath('archivos.0.motivo', 'Otra confirmación está procesando este archivo.');
        $this->assertSame(0, ProductoImagen::count());

        $registro = ProductoImagenLote::query()->where('uuid', $lote['uuid'])->firstOrFail();
        $registro->update(['expires_at' => now()->subHour()]);
        $this->artisan('productos-imagenes:limpiar-temporales')->assertSuccessful();
        $this->assertSame('expirado', $registro->fresh()->estado);
        $this->assertSame('expirado', $archivo->fresh()->estado);

        config(['productos_imagenes.lote_max_archivos' => 1]);
        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.lotes.analizar'), [
                'archivos' => [$this->png('a.png'), $this->png('b.png')],
            ], $this->aceptaJson())
            ->assertStatus(422);
        $this->assertSame(1, ProductoImagenLote::count());
    }

    public function test_el_sku_no_asocia_si_no_hay_folio(): void
    {
        $producto = $this->producto(930020, 'SKUVISIBLE');
        $gestor = $this->usuario();
        $lote = $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.lotes.analizar'), [
                'archivos' => [$this->png('SKUVISIBLE.png')],
            ], $this->aceptaJson())
            ->assertOk()
            ->json('lote');

        $this->assertSame('sin_folio', $lote['archivos'][0]['clasificacion']);
        $this->assertNull($lote['archivos'][0]['producto_id']);
        $this->assertStringContainsString('SKUVISIBLE', (string) $lote['archivos'][0]['apoyo_sku']);
    }

    private function usuario(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['gestion_interna.productos.ver', 'gestion_interna.productos.gestionar']);

        return $user;
    }

    private function producto(int $folio, string $sku): Producto
    {
        return Producto::create([
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'sku' => $sku,
            'descripcion' => 'Producto '.$sku,
            'activo' => true,
        ]);
    }

    private function texto(string $nombre): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($ruta, 'no es imagen');

        return new UploadedFile($ruta, $nombre, 'text/plain', \UPLOAD_ERR_OK, true);
    }

    private function png(string $nombre): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'png');
        $imagen = imagecreatetruecolor(8, 8);
        imagepng($imagen, $ruta);
        imagedestroy($imagen);

        return new UploadedFile($ruta, $nombre, 'image/png', \UPLOAD_ERR_OK, true);
    }

    /**
     * @return array<string, string>
     */
    private function aceptaJson(): array
    {
        return ['Accept' => 'application/json'];
    }
}
