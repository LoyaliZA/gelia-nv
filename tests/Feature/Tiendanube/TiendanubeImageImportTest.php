<?php

namespace Tests\Feature\Tiendanube;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeImageImport;
use App\Models\Tiendanube\TiendanubeImageImportItem;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use App\Services\Tiendanube\TiendanubeImageImportService;
use App\Services\Tiendanube\TiendanubeImageSkuParser;
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

    public function test_endpoint_importar_requiere_permiso(): void
    {
        Permission::findOrCreate('tiendanube.ver', 'web');
        Permission::findOrCreate('tiendanube.productos.editar', 'web');
        $user = User::factory()->create();

        $zip = $this->makeZip(['X.webp' => 'b']);

        $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
            ->post(route('tiendanube.imagenes.importar'), ['zip' => $zip])
            ->assertForbidden();

        $user->givePermissionTo(['tiendanube.ver', 'tiendanube.productos.editar']);

        Http::fake();

        $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
            ->post(route('tiendanube.imagenes.importar'), [
                'zip' => $this->makeZip(['Y.webp' => 'b']),
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);
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
