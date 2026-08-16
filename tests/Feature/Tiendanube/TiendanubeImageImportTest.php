<?php

namespace Tests\Feature\Tiendanube;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeImageImport;
use App\Models\Tiendanube\TiendanubeImageImportItem;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use App\Services\Tiendanube\TiendanubeImageImportService;
use App\Services\Tiendanube\TiendanubeImageSkuParser;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

class TiendanubeImageImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tiendanube.api_base' => 'https://api.tiendanube.com/v1',
            'tiendanube.user_agent' => 'Gelianv',
        ]);

        Storage::fake('local');

        config(['queue.default' => 'sync']);

        TiendanubeConfiguracion::obtener()->fill([
            'store_id' => 8004291,
            'app_id' => '37163',
            'access_token' => Crypt::encryptString('token-test'),
        ])->save();
    }

    public function test_parser_sku_y_position(): void
    {
        $this->assertSame(
            ['sku' => '6294015149371', 'position' => 1, 'extension' => 'webp'],
            TiendanubeImageSkuParser::parse('6294015149371.webp')
        );
        $this->assertSame(
            ['sku' => '6294015149371', 'position' => 2, 'extension' => 'jpg'],
            TiendanubeImageSkuParser::parse('6294015149371_2.jpg')
        );
        $this->assertNull(TiendanubeImageSkuParser::parse('readme.txt'));
    }

    public function test_import_sku_conocido_sube_imagen(): void
    {
        TiendanubeProducto::create(['id' => 100, 'name' => ['es' => 'Prod'], 'published' => true]);
        TiendanubeProductoVariante::create([
            'id' => 1,
            'producto_id' => 100,
            'sku' => 'SKU123',
            'price' => 10,
        ]);

        Http::fake([
            'api.tiendanube.com/v1/8004291/products/100/images' => Http::response([
                'id' => 555,
                'src' => 'https://cdn.tiendanube.com/SKU123.webp',
                'position' => 1,
                'product_id' => 100,
            ], 201),
        ]);

        $zip = $this->makeZip([
            'SKU123.webp' => 'fake-image-bytes',
        ]);

        $import = app(TiendanubeImageImportService::class)->iniciarDesdeZip($zip);

        $this->assertSame('completado', $import->fresh()->estado);
        $this->assertSame(1, $import->fresh()->exitosos);

        $item = TiendanubeImageImportItem::where('import_id', $import->id)->first();
        $this->assertSame('ok', $item->estado);
        $this->assertSame(555, $item->imagen_tn_id);

        $this->assertDatabaseHas('tiendanube_producto_imagenes', [
            'id' => 555,
            'producto_id' => 100,
        ]);
    }

    public function test_import_sku_desconocido_no_llama_api(): void
    {
        Http::fake();

        $zip = $this->makeZip([
            'NOEXISTE.webp' => 'bytes',
        ]);

        $import = app(TiendanubeImageImportService::class)->iniciarDesdeZip($zip);
        $import->refresh();

        $this->assertSame(1, $import->fallidos);
        $item = $import->items()->first();
        $this->assertSame('error', $item->estado);
        $this->assertSame(TiendanubeImageImportService::MOTIVO_SKU_NO_ENCONTRADO, $item->motivo);
        $this->assertStringContainsString('SKU no encontrado', (string) $item->mensaje);

        Http::assertNothingSent();
    }

    public function test_import_nombre_invalido_omite_con_motivo(): void
    {
        Http::fake();

        // Filename solo espacios → parser no obtiene SKU
        $zip = $this->makeZip([
            '   .webp' => 'bytes',
            'NOEXISTE.webp' => 'bytes',
        ]);

        $import = app(TiendanubeImageImportService::class)->iniciarDesdeZip($zip);

        $omitido = $import->items()->where('estado', 'omitido')->first();
        $this->assertNotNull($omitido);
        $this->assertSame(TiendanubeImageImportService::MOTIVO_NOMBRE_INVALIDO, $omitido->motivo);

        $sinSku = $import->items()->where('motivo', TiendanubeImageImportService::MOTIVO_SKU_NO_ENCONTRADO)->first();
        $this->assertNotNull($sinSku);

        Http::assertNothingSent();
    }

    public function test_progreso_incluye_resumen_por_motivo(): void
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('tiendanube.ver');

        Http::fake();

        $import = app(TiendanubeImageImportService::class)->iniciarDesdeZip(
            $this->makeZip(['FALTANTE.webp' => 'bytes'])
        );

        $this->actingAs($user)
            ->getJson(route('tiendanube.imagenes.importar.progreso', $import->id))
            ->assertOk()
            ->assertJsonPath('resumen.sku_no_encontrado', 1)
            ->assertJsonPath('errores_total', 1)
            ->assertJsonPath('errores.0.motivo', TiendanubeImageImportService::MOTIVO_SKU_NO_ENCONTRADO);
    }

    public function test_reporte_csv_incluye_omitidos(): void
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('tiendanube.ver');

        Http::fake();

        $import = app(TiendanubeImageImportService::class)->iniciarDesdeZip(
            $this->makeZip([
                '   .webp' => 'bytes',
                'SINPRODUCTO.webp' => 'bytes',
            ])
        );

        $response = $this->actingAs($user)
            ->get(route('tiendanube.imagenes.importar.reporte', $import->id));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('filename,sku,position,estado,motivo,mensaje,producto_id', $csv);
        $this->assertStringContainsString('nombre_invalido', $csv);
        $this->assertStringContainsString('sku_no_encontrado', $csv);
        $this->assertStringContainsString('SINPRODUCTO.webp', $csv);
    }

    public function test_import_position_n_envia_position_2(): void
    {
        TiendanubeProducto::create(['id' => 200, 'name' => ['es' => 'P'], 'published' => true]);
        TiendanubeProductoVariante::create([
            'id' => 2,
            'producto_id' => 200,
            'sku' => 'ABC',
            'price' => 1,
        ]);

        Http::fake([
            'api.tiendanube.com/v1/8004291/products/200/images' => Http::response([
                'id' => 900,
                'src' => 'https://cdn.example.com/x.jpg',
                'position' => 2,
                'product_id' => 200,
            ], 201),
        ]);

        $zip = $this->makeZip([
            'ABC_2.jpg' => 'bytes',
        ]);

        $import = app(TiendanubeImageImportService::class)->iniciarDesdeZip($zip);
        $item = $import->items()->first();
        $this->assertSame(2, $item->position);
        $this->assertSame('ok', $item->fresh()->estado);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/products/200/images')
                && ($request['position'] ?? null) == 2;
        });
    }

    public function test_import_reencola_lotes_hasta_completar(): void
    {
        $files = [];
        $n = TiendanubeImageImportService::BATCH_SIZE + 3;
        for ($i = 1; $i <= $n; $i++) {
            $sku = 'SKU'.$i;
            TiendanubeProducto::create(['id' => 1000 + $i, 'name' => ['es' => 'P'.$i], 'published' => true]);
            TiendanubeProductoVariante::create([
                'id' => 1000 + $i,
                'producto_id' => 1000 + $i,
                'sku' => $sku,
                'price' => 1,
            ]);
            $files[$sku.'.webp'] = 'bytes'.$i;
        }

        Http::fake(function ($request) {
            static $id = 5000;
            $id++;

            return Http::response([
                'id' => $id,
                'src' => 'https://cdn.example.com/'.$id.'.webp',
                'position' => 1,
            ], 201);
        });

        $import = app(TiendanubeImageImportService::class)->iniciarDesdeZip($this->makeZip($files));
        $import->refresh();

        $this->assertSame('completado', $import->estado);
        $this->assertSame($n, $import->exitosos);
        $this->assertSame($n, $import->total_archivos);
        $this->assertSame(0, $import->items()->where('estado', 'pendiente')->count());
    }

    public function test_endpoint_importar_requiere_permiso(): void
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.productos.editar', 'web');
        $user = User::factory()->create();

        $zip = $this->makeZip(['X.webp' => 'b']);

        $this->actingAs($user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('tiendanube.imagenes.importar'), ['zip' => $zip])
            ->assertForbidden();

        $user->givePermissionTo(['tiendanube.ver', 'tiendanube.productos.editar']);

        Http::fake();

        $this->actingAs($user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('tiendanube.imagenes.importar'), [
                'zip' => $this->makeZip(['Y.webp' => 'b']),
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_endpoint_importar_persiste_opciones_optimizar(): void
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.productos.editar', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(['tiendanube.ver', 'tiendanube.productos.editar']);

        Http::fake();

        $res = $this->actingAs($user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('tiendanube.imagenes.importar'), [
                'zip' => $this->makeZip(['Z.webp' => 'b']),
                'convertir_webp' => '0',
                'modo_1280' => 'square',
            ])
            ->assertCreated();

        $importId = $res->json('import_id');
        $this->assertNotNull($importId);
        $this->assertDatabaseHas('tiendanube_image_imports', [
            'id' => $importId,
            'convertir_webp' => 0,
            'modo_1280' => 'square',
        ]);
    }

    public function test_import_desde_archivos_indexa_y_sube(): void
    {
        TiendanubeProducto::create(['id' => 77, 'name' => ['es' => 'P'], 'published' => true]);
        TiendanubeProductoVariante::create([
            'id' => 77,
            'producto_id' => 77,
            'sku' => 'FILE77',
            'price' => 1,
        ]);

        Http::fake([
            'api.tiendanube.com/v1/8004291/products/77/images' => Http::response([
                'id' => 777,
                'src' => 'https://cdn.example.com/FILE77.webp',
                'position' => 1,
            ], 201),
        ]);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );
        $path = sys_get_temp_dir().'/tn_file_'.uniqid('', true).'.png';
        file_put_contents($path, $png);
        $upload = new UploadedFile($path, 'FILE77.png', 'image/png', null, true);

        $import = app(TiendanubeImageImportService::class)->iniciarDesdeArchivos([$upload], null, true);
        $import->refresh();

        $this->assertSame('completado', $import->estado);
        $this->assertSame(1, $import->exitosos);
        $this->assertTrue($import->reemplazar_primera);
        $this->assertDatabaseHas('tiendanube_producto_imagenes', [
            'id' => 777,
            'producto_id' => 77,
        ]);
    }

    public function test_reporte_alertas_y_sin_foto_csv(): void
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('tiendanube.ver');

        TiendanubeProducto::create(['id' => 1, 'name' => ['es' => 'Con alerta'], 'published' => true]);
        TiendanubeProductoVariante::create(['id' => 1, 'producto_id' => 1, 'sku' => 'A1', 'price' => 1]);
        TiendanubeProductoImagen::create([
            'id' => 10,
            'producto_id' => 1,
            'src' => 'https://cdn.example.com/a.jpg',
            'position' => 1,
            'width' => 500,
            'height' => 600,
            'alerta_pequena' => true,
            'alerta_no_cuadrada' => true,
            'requiere_revision' => true,
        ]);
        TiendanubeProducto::create(['id' => 3, 'name' => ['es' => 'Solo pequeña'], 'published' => true]);
        TiendanubeProductoVariante::create(['id' => 3, 'producto_id' => 3, 'sku' => 'C3', 'price' => 1]);
        TiendanubeProductoImagen::create([
            'id' => 11,
            'producto_id' => 3,
            'src' => 'https://cdn.example.com/c.jpg',
            'position' => 1,
            'width' => 400,
            'height' => 400,
            'alerta_pequena' => true,
            'alerta_no_cuadrada' => false,
            'requiere_revision' => true,
        ]);
        TiendanubeProducto::create(['id' => 4, 'name' => ['es' => 'Solo no cuadrada'], 'published' => true]);
        TiendanubeProductoVariante::create(['id' => 4, 'producto_id' => 4, 'sku' => 'D4', 'price' => 1]);
        TiendanubeProductoImagen::create([
            'id' => 12,
            'producto_id' => 4,
            'src' => 'https://cdn.example.com/d.jpg',
            'position' => 1,
            'width' => 1200,
            'height' => 900,
            'alerta_pequena' => false,
            'alerta_no_cuadrada' => true,
            'requiere_revision' => true,
        ]);

        TiendanubeProducto::create(['id' => 2, 'name' => ['es' => 'Sin foto'], 'published' => true]);
        TiendanubeProductoVariante::create(['id' => 2, 'producto_id' => 2, 'sku' => 'B2', 'price' => 1]);
        TiendanubeProducto::create(['id' => 5, 'name' => ['es' => 'Sin foto draft'], 'published' => false]);
        TiendanubeProductoVariante::create(['id' => 5, 'producto_id' => 5, 'sku' => 'E5', 'price' => 1]);

        $alertas = $this->actingAs($user)->get(route('tiendanube.imagenes.reporte_alertas'));
        $alertas->assertOk();
        $csvAlertas = $alertas->streamedContent();
        $this->assertStringContainsString('detalle', $csvAlertas);
        $this->assertStringContainsString('lado menor < 800px', $csvAlertas);
        $this->assertStringContainsString('no cuadrada', $csvAlertas);
        $this->assertStringContainsString('A1', $csvAlertas);

        $soloPequena = $this->actingAs($user)->get(route('tiendanube.imagenes.reporte_alertas', [
            'detalle' => ['pequena'],
        ]));
        $csvPequena = $soloPequena->streamedContent();
        $this->assertStringContainsString('C3', $csvPequena);
        $this->assertStringContainsString('A1', $csvPequena);
        $this->assertStringNotContainsString('D4', $csvPequena);

        $soloNoCuadrada = $this->actingAs($user)->get(route('tiendanube.imagenes.reporte_alertas', [
            'detalle' => ['no_cuadrada'],
        ]));
        $csvNoCuadrada = $soloNoCuadrada->streamedContent();
        $this->assertStringContainsString('D4', $csvNoCuadrada);
        $this->assertStringNotContainsString('C3', $csvNoCuadrada);

        $sinFoto = $this->actingAs($user)->get(route('tiendanube.imagenes.reporte_sin_foto'));
        $sinFoto->assertOk();
        $csvSin = $sinFoto->streamedContent();
        $this->assertStringContainsString('Sin foto', $csvSin);
        $this->assertStringContainsString('sin imagen', $csvSin);
        $this->assertStringContainsString('publicado', $csvSin);
        $this->assertStringContainsString('B2', $csvSin);
        $this->assertStringContainsString('E5', $csvSin);
        $this->assertStringNotContainsString('Con alerta', $csvSin);

        $sinFotoPub = $this->actingAs($user)->get(route('tiendanube.imagenes.reporte_sin_foto', [
            'publicado' => 1,
        ]));
        $csvPub = $sinFotoPub->streamedContent();
        $this->assertStringContainsString('B2', $csvPub);
        $this->assertStringNotContainsString('E5', $csvPub);
    }

    public function test_progreso_incluye_alertas_dimension(): void
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('tiendanube.ver');

        $import = TiendanubeImageImport::create([
            'estado' => 'completado',
            'total_archivos' => 1,
            'procesados' => 1,
            'exitosos' => 1,
            'fallidos' => 0,
            'reemplazar_primera' => true,
        ]);
        TiendanubeProducto::create(['id' => 5, 'name' => ['es' => 'P'], 'published' => true]);
        TiendanubeProductoImagen::create([
            'id' => 55,
            'producto_id' => 5,
            'src' => 'https://cdn.example.com/x.jpg',
            'position' => 1,
            'requiere_revision' => true,
            'alerta_pequena' => true,
            'alerta_no_cuadrada' => false,
        ]);
        TiendanubeImageImportItem::create([
            'import_id' => $import->id,
            'filename' => 'x.jpg',
            'sku' => 'X',
            'position' => 1,
            'producto_id' => 5,
            'estado' => 'ok',
            'imagen_tn_id' => 55,
        ]);

        $this->actingAs($user)
            ->getJson(route('tiendanube.imagenes.importar.progreso', $import->id))
            ->assertOk()
            ->assertJsonPath('alertas_dimension', 1);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function makeZip(array $files): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'tnzip').'.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return new UploadedFile($path, 'imagenes.zip', 'application/zip', null, true);
    }
}
