<?php

namespace Tests\Feature\Productos;

use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\ProductoImagenLimpieza;
use App\Models\User;
use App\Services\Productos\Imagenes\AlmacenArchivoImagenProducto;
use App\Services\Productos\Imagenes\GenerarMiniaturaImagenProducto;
use App\Services\Productos\Imagenes\GestionarGaleriaProductoService;
use App\Services\Productos\Imagenes\ValidarArchivoImagenProducto;
use Closure;
use FilesystemIterator;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class ProductoImagenEscrituraFalloTest extends TestCase
{
    use RefreshDatabaseSafe;

    private string $raiz = '';

    private string $modoEscritura = 'ok';

    private int $originalesEscritos = 0;

    public function createApplication()
    {
        $app = parent::createApplication();
        $conexion = $app['config']->get('database.default');
        $base = $app['config']->get('database.connections.'.$conexion.'.database');
        if ($conexion !== 'sqlite' || $base !== ':memory:') {
            throw new RuntimeException('La conexión de prueba no está aislada de la base de operación.');
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
        $this->raiz = sys_get_temp_dir().'/gelia-img-fallo-'.bin2hex(random_bytes(4));
        mkdir($this->raiz, 0700, true);
        $this->montarDisco();
        foreach ([
            'gestion_interna.productos.ver',
            'gestion_interna.productos.gestionar',
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    protected function tearDown(): void
    {
        if ($this->raiz !== '' && is_dir($this->raiz)) {
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->raiz, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->raiz);
        }
        parent::tearDown();
    }

    public function test_alta_no_confirma_si_write_stream_devuelve_false(): void
    {
        $producto = $this->producto();
        $this->modoEscritura = 'falso';

        $this->actingAs($this->gestor())
            ->post(route('gestion_interna.productos.imagenes.store', $producto), [
                'archivo' => $this->png(),
            ], $this->aceptaJson())
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');

        $this->assertSame(0, ProductoImagen::count());
        $this->assertSame([], $this->parciales());
        $this->assertReferenciasCoherentes();
    }

    public function test_reemplazo_conserva_la_imagen_si_write_stream_devuelve_false(): void
    {
        $producto = $this->producto();
        $gestor = $this->gestor();
        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.store', $producto), [
                'archivo' => $this->png(),
            ], $this->aceptaJson())
            ->assertOk();
        $imagen = ProductoImagen::query()->firstOrFail();
        $original = $imagen->clave_original;
        $miniatura = $imagen->clave_miniatura;
        $marca = $producto->updated_at?->toJSON();
        $this->modoEscritura = 'falso';

        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.reemplazar', [$producto, $imagen]), [
                'archivo' => $this->png(),
            ], $this->aceptaJson())
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');

        $fresca = $imagen->fresh();
        $this->assertSame($original, $fresca->clave_original);
        $this->assertSame($miniatura, $fresca->clave_miniatura);
        $this->assertSame('lista', $fresca->estado);
        Storage::disk('imagenes_prueba')->assertExists($original);
        $this->assertNotNull($miniatura);
        Storage::disk('imagenes_prueba')->assertExists($miniatura);
        $this->assertSame([], $this->parciales());
        $this->assertSame($marca, $producto->fresh()->updated_at?->toJSON());
        $this->assertNull($producto->fresh()->imagen_path);
        $this->assertReferenciasCoherentes();
    }

    public function test_escritura_que_lanza_excepcion_conserva_la_referencia_anterior(): void
    {
        $producto = $this->producto();
        $gestor = $this->gestor();
        $this->modoEscritura = 'excepcion';

        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.store', $producto), [
                'archivo' => $this->png(),
            ], $this->aceptaJson())
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');
        $this->assertSame(0, ProductoImagen::count());
        $this->assertSame([], $this->parciales());

        $this->modoEscritura = 'ok';
        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.store', $producto), [
                'archivo' => $this->png(),
            ], $this->aceptaJson())
            ->assertOk();
        $imagen = ProductoImagen::query()->firstOrFail();
        $original = $imagen->clave_original;
        $miniatura = $imagen->clave_miniatura;
        $this->modoEscritura = 'excepcion';

        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.reemplazar', [$producto, $imagen]), [
                'archivo' => $this->png(),
            ], $this->aceptaJson())
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');

        $this->assertSame($original, $imagen->fresh()->clave_original);
        $this->assertSame($miniatura, $imagen->fresh()->clave_miniatura);
        Storage::disk('imagenes_prueba')->assertExists($original);
        $this->assertNotNull($miniatura);
        Storage::disk('imagenes_prueba')->assertExists($miniatura);
        $this->assertSame([], $this->parciales());
        $this->assertSame(1, ProductoImagen::count());
        $this->assertReferenciasCoherentes();
    }

    public function test_fallo_al_guardar_miniatura_conserva_el_original_recuperable(): void
    {
        $producto = $this->producto();
        $this->modoEscritura = 'miniatura';

        $this->actingAs($this->gestor())
            ->post(route('gestion_interna.productos.imagenes.store', $producto), [
                'archivo' => $this->png(),
            ], $this->aceptaJson())
            ->assertOk()
            ->assertJsonPath('estado', 'miniatura_error');

        $imagen = ProductoImagen::query()->firstOrFail();
        $this->assertSame('miniatura_error', $imagen->estado);
        $this->assertNull($imagen->clave_miniatura);
        Storage::disk('imagenes_prueba')->assertExists($imagen->clave_original);
        $this->assertNotFalse(@getimagesize(Storage::disk('imagenes_prueba')->path($imagen->clave_original)));
        $this->assertSame([], $this->parciales());
        $this->assertReferenciasCoherentes();
    }

    public function test_lote_informa_el_archivo_fallido_y_completa_el_otro(): void
    {
        $primero = $this->producto();
        $segundo = $this->producto();
        $gestor = $this->gestor();
        $this->modoEscritura = 'segundo-original';
        $nombrePrimero = $primero->folio.'-uno.png';
        $nombreSegundo = $segundo->folio.'-dos.png';

        $lote = $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.lotes.analizar'), [
                'archivos' => [
                    $this->png($nombrePrimero),
                    $this->png($nombreSegundo),
                ],
            ], $this->aceptaJson())
            ->assertOk()
            ->json('lote');
        $porNombre = collect($lote['archivos'])->keyBy('nombre_original');

        $confirmacion = $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.lotes.confirmar', $lote['uuid']), [
                'archivos' => [
                    ['id' => $porNombre[$nombrePrimero]['id'], 'accion' => 'confirmar'],
                    ['id' => $porNombre[$nombreSegundo]['id'], 'accion' => 'confirmar'],
                ],
            ], $this->aceptaJson())
            ->assertOk()
            ->json('archivos');

        $resultado = collect($confirmacion)->keyBy('id');
        $this->assertSame('completado', $resultado[$porNombre[$nombrePrimero]['id']]['estado']);
        $this->assertNotNull($resultado[$porNombre[$nombrePrimero]['id']]['imagen_id']);
        $this->assertSame('error', $resultado[$porNombre[$nombreSegundo]['id']]['estado']);
        $this->assertNull($resultado[$porNombre[$nombreSegundo]['id']]['imagen_id']);
        $this->assertSame(1, ProductoImagen::query()->where('producto_id', $primero->id)->count());
        $this->assertSame(0, ProductoImagen::query()->where('producto_id', $segundo->id)->count());
        $this->assertSame([], $this->parciales());
        $this->assertNull($primero->fresh()->imagen_path);
        $this->assertNull($segundo->fresh()->imagen_path);
        $this->assertReferenciasCoherentes();
    }

    public function test_reintento_de_miniatura_sobre_la_version_vigente(): void
    {
        $producto = $this->producto();
        $gestor = $this->gestor();
        $marca = $producto->updated_at?->toJSON();
        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.store', $producto), [
                'archivo' => $this->png(),
            ], $this->aceptaJson())
            ->assertOk();
        $imagen = ProductoImagen::query()->firstOrFail();
        $original = $imagen->clave_original;
        Storage::disk('imagenes_prueba')->delete($imagen->clave_miniatura);
        $imagen->update(['clave_miniatura' => null, 'estado' => 'miniatura_error']);

        $this->actingAs($gestor)
            ->post(route('gestion_interna.productos.imagenes.miniatura', [$producto, $imagen]), [], $this->aceptaJson())
            ->assertOk()
            ->assertJsonPath('estado', 'lista');

        $fresca = $imagen->fresh();
        $this->assertSame($original, $fresca->clave_original);
        $this->assertSame('lista', $fresca->estado);
        $this->assertSame(dirname($original), dirname((string) $fresca->clave_miniatura));
        Storage::disk('imagenes_prueba')->assertExists($original);
        Storage::disk('imagenes_prueba')->assertExists($fresca->clave_miniatura);
        $this->assertSame($marca, $producto->fresh()->updated_at?->toJSON());
        $this->assertReferenciasCoherentes();
    }

    public function test_reintento_intercalado_con_reemplazo_conserva_la_version_nueva(): void
    {
        $producto = $this->producto();
        $marca = $producto->updated_at?->toJSON();
        $this->actingAs($this->gestor())
            ->post(route('gestion_interna.productos.imagenes.store', $producto), [
                'archivo' => $this->png(),
            ], $this->aceptaJson())
            ->assertOk();
        $imagen = ProductoImagen::query()->firstOrFail();
        $originalA = $imagen->clave_original;
        $miniaturaA = $imagen->clave_miniatura;
        $archivos = app(AlmacenArchivoImagenProducto::class);
        $galeriaReal = new GestionarGaleriaProductoService(
            app(ValidarArchivoImagenProducto::class),
            $archivos,
            new GenerarMiniaturaImagenProducto($archivos),
        );
        $reemplazo = $this->png();
        $mini = new class($archivos, function () use ($galeriaReal, $producto, $imagen, $reemplazo) {
            $galeriaReal->reemplazar($producto, $imagen->fresh(), $reemplazo);
        }) extends GenerarMiniaturaImagenProducto {

            public function __construct(
                AlmacenArchivoImagenProducto $archivos,
                private Closure $despuesDeGenerar,
            ) {
                parent::__construct($archivos);
            }

            public function generar(string $claveOriginal): string
            {
                $clave = parent::generar($claveOriginal);
                ($this->despuesDeGenerar)();

                return $clave;
            }
        };
        $galeria = new GestionarGaleriaProductoService(
            app(ValidarArchivoImagenProducto::class),
            $archivos,
            $mini,
        );

        $resultado = $galeria->reintentarMiniatura($producto, $imagen);

        $this->assertNotSame($originalA, $resultado->clave_original);
        $this->assertSame(dirname($resultado->clave_original), dirname((string) $resultado->clave_miniatura));
        $this->assertNotSame($miniaturaA, $resultado->clave_miniatura);
        Storage::disk('imagenes_prueba')->assertExists($resultado->clave_original);
        Storage::disk('imagenes_prueba')->assertExists($resultado->clave_miniatura);
        $this->assertFalse(ProductoImagen::query()->where('clave_original', $originalA)->orWhere('clave_miniatura', $miniaturaA)->exists());
        $this->assertSame($marca, $producto->fresh()->updated_at?->toJSON());
        $this->assertSame($producto->folio, $producto->fresh()->folio);
        $this->assertReferenciasCoherentes();
        $this->assertDirectorioRetirado(dirname($originalA), [
            $resultado->clave_original,
            $resultado->clave_miniatura,
        ]);
    }

    public function test_fallo_de_reintento_no_destruye_una_imagen_valida(): void
    {
        $producto = $this->producto();
        $this->actingAs($this->gestor())
            ->post(route('gestion_interna.productos.imagenes.store', $producto), [
                'archivo' => $this->png(),
            ], $this->aceptaJson())
            ->assertOk();
        $imagen = ProductoImagen::query()->firstOrFail();
        $original = $imagen->clave_original;
        $miniatura = $imagen->clave_miniatura;
        $bytesOriginal = Storage::disk('imagenes_prueba')->get($original);
        $bytesMiniatura = Storage::disk('imagenes_prueba')->get($miniatura);
        $archivos = app(AlmacenArchivoImagenProducto::class);
        $mini = new class($archivos) extends GenerarMiniaturaImagenProducto
        {
            public function generar(string $claveOriginal): string
            {
                throw ValidationException::withMessages([
                    'miniatura' => 'No se pudo generar la miniatura.',
                ]);
            }
        };
        $galeria = new GestionarGaleriaProductoService(
            app(ValidarArchivoImagenProducto::class),
            $archivos,
            $mini,
        );

        $resultado = $galeria->reintentarMiniatura($producto, $imagen);

        $this->assertSame($original, $resultado->clave_original);
        $this->assertSame($miniatura, $resultado->clave_miniatura);
        $this->assertSame('lista', $resultado->estado);
        $this->assertSame($bytesOriginal, Storage::disk('imagenes_prueba')->get($original));
        $this->assertSame($bytesMiniatura, Storage::disk('imagenes_prueba')->get($miniatura));

        $imagen->update(['clave_miniatura' => null, 'estado' => 'miniatura_error']);
        $this->modoEscritura = 'miniatura';
        $this->actingAs($this->gestor())
            ->post(route('gestion_interna.productos.imagenes.miniatura', [$producto, $imagen]), [], $this->aceptaJson())
            ->assertOk()
            ->assertJsonPath('estado', 'miniatura_error');
        $fallida = $imagen->fresh();
        $this->assertSame($original, $fallida->clave_original);
        $this->assertNull($fallida->clave_miniatura);
        $this->assertSame('miniatura_error', $fallida->estado);
        $this->assertSame($bytesOriginal, Storage::disk('imagenes_prueba')->get($original));
        $this->assertSame([], $this->parciales());
        $this->assertReferenciasCoherentes();
    }

    public function test_reemplazo_y_desvinculacion_usan_las_claves_de_la_fila_vigente(): void
    {
        $producto = $this->producto();
        $this->actingAs($this->gestor())
            ->post(route('gestion_interna.productos.imagenes.store', $producto), [
                'archivo' => $this->png(),
            ], $this->aceptaJson())
            ->assertOk();
        $stale = ProductoImagen::query()->firstOrFail();
        $claveA = $stale->clave_original;
        $miniaturaA = $stale->clave_miniatura;
        $claveC = 'productos/imagenes/'.$producto->id.'/'.Str::uuid().'/original.png';
        $miniaturaC = dirname($claveC).'/miniatura.webp';
        Storage::disk('imagenes_prueba')->put($claveC, 'original-c');
        Storage::disk('imagenes_prueba')->put($miniaturaC, 'mini-c');
        ProductoImagen::query()->whereKey($stale->id)->update([
            'clave_original' => $claveC,
            'clave_miniatura' => $miniaturaC,
        ]);
        $galeria = app(GestionarGaleriaProductoService::class);

        $reemplazada = $galeria->reemplazar($producto, $stale, $this->png());

        $this->assertNotSame($claveA, $reemplazada->clave_original);
        $this->assertNotSame($claveC, $reemplazada->clave_original);
        Storage::disk('imagenes_prueba')->assertExists($reemplazada->clave_original);
        Storage::disk('imagenes_prueba')->assertExists($claveA);
        Storage::disk('imagenes_prueba')->assertMissing($claveC);
        Storage::disk('imagenes_prueba')->assertMissing($miniaturaC);
        $this->assertReferenciasCoherentes();

        $otra = $this->producto();
        $this->actingAs($this->gestor())
            ->post(route('gestion_interna.productos.imagenes.store', $otra), [
                'archivo' => $this->png(),
            ], $this->aceptaJson())
            ->assertOk();
        $staleBorrado = ProductoImagen::query()->where('producto_id', $otra->id)->firstOrFail();
        $clavePrevia = $staleBorrado->clave_original;
        $claveVigente = 'productos/imagenes/'.$otra->id.'/'.Str::uuid().'/original.png';
        $miniaturaVigente = dirname($claveVigente).'/miniatura.webp';
        Storage::disk('imagenes_prueba')->put($claveVigente, 'original-vigente');
        Storage::disk('imagenes_prueba')->put($miniaturaVigente, 'mini-vigente');
        ProductoImagen::query()->whereKey($staleBorrado->id)->update([
            'clave_original' => $claveVigente,
            'clave_miniatura' => $miniaturaVigente,
        ]);

        $galeria->desvincular($otra, $staleBorrado);

        $this->assertNull(ProductoImagen::query()->find($staleBorrado->id));
        Storage::disk('imagenes_prueba')->assertExists($clavePrevia);
        Storage::disk('imagenes_prueba')->assertMissing($claveVigente);
        Storage::disk('imagenes_prueba')->assertMissing($miniaturaVigente);
    }

    private function gestor(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['gestion_interna.productos.ver', 'gestion_interna.productos.gestionar']);

        return $user;
    }

    private function producto(): Producto
    {
        static $folio = 940000;
        $folio++;

        return Producto::create([
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'sku' => 'SKU'.$folio,
            'descripcion' => 'Producto '.$folio,
            'activo' => true,
        ]);
    }

    private function montarDisco(): void
    {
        $adapter = new DiscoImagenConFalloEscritura($this->raiz, fn (string $path): string => $this->decision($path));
        $filesystem = new Filesystem($adapter);
        $disk = new FilesystemAdapter($filesystem, $adapter, [
            'driver' => 'local',
            'root' => $this->raiz,
            'throw' => false,
        ]);
        config([
            'filesystems.disks.imagenes_prueba' => [
                'driver' => 'local',
                'root' => $this->raiz,
                'throw' => false,
            ],
            'productos_imagenes.disk' => 'imagenes_prueba',
        ]);
        Storage::set('imagenes_prueba', $disk);
    }

    private function decision(string $path): string
    {
        if ($this->modoEscritura === 'miniatura' && str_contains($path, 'miniatura')) {
            return 'falso';
        }
        if ($this->modoEscritura === 'falso' && str_contains($path, '/original.')) {
            return 'falso';
        }
        if ($this->modoEscritura === 'excepcion' && str_contains($path, '/original.')) {
            return 'excepcion';
        }
        if ($this->modoEscritura === 'segundo-original' && str_contains($path, '/original.')) {
            $this->originalesEscritos++;

            return $this->originalesEscritos === 2 ? 'falso' : 'ok';
        }

        return 'ok';
    }

    /**
     * @return list<string>
     */
    private function parciales(): array
    {
        $encontrados = [];
        if (! is_dir($this->raiz)) {
            return $encontrados;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->raiz, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($items as $item) {
            if ($item->isFile() && file_get_contents($item->getPathname()) === 'parcial') {
                $encontrados[] = $item->getPathname();
            }
        }

        return $encontrados;
    }

    private function assertReferenciasCoherentes(): void
    {
        foreach (ProductoImagen::query()->get() as $imagen) {
            $this->assertNotSame('', $imagen->clave_original);
            Storage::disk('imagenes_prueba')->assertExists($imagen->clave_original);
            if ($imagen->estado === 'lista') {
                $this->assertNotNull($imagen->clave_miniatura);
            }
            if ($imagen->clave_miniatura !== null) {
                Storage::disk('imagenes_prueba')->assertExists($imagen->clave_miniatura);
                $this->assertSame(dirname($imagen->clave_original), dirname($imagen->clave_miniatura));
            }
        }
    }

    /**
     * @param  list<string|null>  $vigentes
     */
    private function assertDirectorioRetirado(string $directorio, array $vigentes): void
    {
        $absoluto = $this->raiz.'/'.$directorio;
        if (! is_dir($absoluto)) {
            return;
        }
        $archivos = Storage::disk('imagenes_prueba')->files($directorio);
        foreach ($archivos as $archivo) {
            $this->assertNotContains($archivo, $vigentes);
            $this->assertFalse(
                ProductoImagen::query()->where('clave_original', $archivo)->orWhere('clave_miniatura', $archivo)->exists()
            );
            $this->assertTrue(ProductoImagenLimpieza::query()->where('clave', $archivo)->exists());
        }
    }

    private function png(?string $nombre = null): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'png');
        $imagen = imagecreatetruecolor(8, 8);
        imagepng($imagen, $ruta);
        imagedestroy($imagen);

        return new UploadedFile($ruta, $nombre ?? 'foto.png', 'image/png', \UPLOAD_ERR_OK, true);
    }

    /**
     * @return array<string, string>
     */
    private function aceptaJson(): array
    {
        return ['Accept' => 'application/json'];
    }
}

/**
 * Disco local de prueba. writeStream puede devolver false o lanzar, como los discos con throw=false.
 */
final class DiscoImagenConFalloEscritura extends LocalFilesystemAdapter
{
    private string $raiz;

    public function __construct(
        string $location,
        private Closure $decision,
    ) {
        parent::__construct($location);
        $this->raiz = $location;
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $decision = ($this->decision)($path);
        if ($decision === 'ok') {
            parent::writeStream($path, $contents, $config);

            return;
        }

        $this->dejarParcial($path);
        if ($decision === 'excepcion') {
            throw new RuntimeException('escritura interrumpida');
        }

        throw UnableToWriteFile::atLocation($path, 'escritura rechazada');
    }

    private function dejarParcial(string $path): void
    {
        $absoluta = $this->raiz.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        $directorio = dirname($absoluta);
        if (! is_dir($directorio) && ! mkdir($directorio, 0700, true) && ! is_dir($directorio)) {
            return;
        }
        if (! is_file($absoluta)) {
            file_put_contents($absoluta, 'parcial');
        }
    }
}
