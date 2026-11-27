<?php

namespace Tests\Feature\Facturas;

use App\Http\Requests\Facturas\ActualizarBorradorFacturaRequest;
use App\Http\Requests\Facturas\RepararSolicitudFacturaRequest;
use App\Http\Requests\Facturas\StoreSolicitudFacturaRequest;
use App\Models\SolicitudFactura;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class ComprobantesFacturaValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::shouldReceive('hasColumn')->andReturn(false);
    }

    public static function limitesPorFormato(): iterable
    {
        foreach ([StoreSolicitudFacturaRequest::class, ActualizarBorradorFacturaRequest::class] as $request) {
            foreach (['jpg', 'jpeg', 'png', 'webp', 'pdf', 'zip'] as $extension) {
                $maxKb = $extension === 'zip' ? 10240 : 5120;
                yield "$request $extension límite" => [$request, $extension, $maxKb, true];
                yield "$request $extension excedido" => [$request, $extension, $maxKb + 1 / 1024, false];
            }
        }
    }

    #[DataProvider('limitesPorFormato')]
    public function test_limites_por_formato_en_creacion_y_borrador(string $clase, string $extension, float $kb, bool $valido): void
    {
        $request = new $clase;
        $validator = Validator::make(
            ['vouchers' => [UploadedFile::fake()->create("pago.$extension", $kb)]],
            array_intersect_key($request->rules(), array_flip(['vouchers', 'vouchers.*'])),
            $request->messages()
        );

        $this->assertSame($valido, $validator->passes(), $validator->errors()->toJson());
        if (! $valido) {
            $this->assertStringContainsString($extension === 'zip' ? '10 MB' : '5 MB', $validator->errors()->first('vouchers.0'));
        }
    }

    public function test_conserva_maximo_cinco_comprobantes_de_formatos_combinados(): void
    {
        foreach ([new StoreSolicitudFacturaRequest, new ActualizarBorradorFacturaRequest] as $request) {
            $rules = array_intersect_key($request->rules(), array_flip(['vouchers', 'vouchers.*']));
            $files = array_map(fn ($ext) => UploadedFile::fake()->create("pago.$ext", 10), ['zip', 'pdf', 'jpg', 'png', 'webp']);
            $this->assertTrue(Validator::make(['vouchers' => $files], $rules)->passes());
            $files[] = UploadedFile::fake()->create('sexto.zip', 10);
            $this->assertTrue(Validator::make(['vouchers' => $files], $rules)->errors()->has('vouchers'));
        }
    }

    public function test_valida_contenido_real_del_zip_y_no_solo_su_nombre(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'factura-zip-');
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path, ZipArchive::OVERWRITE));
            $zip->addFromString('comprobante.txt', 'Comprobante de prueba');
            $zip->close();
            $rules = (new StoreSolicitudFacturaRequest)->rules()['vouchers.*'];
            $this->assertTrue(Validator::make(['archivo' => new UploadedFile($path, 'pago.zip', null, null, true)], ['archivo' => $rules])->passes());

            file_put_contents($path, 'Esto es texto, no un ZIP');
            $this->assertTrue(Validator::make(['archivo' => new UploadedFile($path, 'falso.zip', 'application/zip', null, true)], ['archivo' => $rules])->errors()->has('archivo'));

            file_put_contents($path, "%PDF-1.4\n".str_repeat(' ', 5120 * 1024));
            $validator = Validator::make(['archivo' => new UploadedFile($path, 'renombrado.zip', 'application/zip', null, true)], ['archivo' => $rules]);
            $this->assertStringContainsString('5 MB', $validator->errors()->first('archivo'));
        } finally {
            unlink($path);
        }
    }

    public function test_conserva_restricciones_de_archivo_fiscal_y_correccion(): void
    {
        foreach ([new StoreSolicitudFacturaRequest, new ActualizarBorradorFacturaRequest] as $request) {
            $rules = ['archivo_fiscal' => $request->rules()['archivo_fiscal']];
            $this->assertTrue(Validator::make(['archivo_fiscal' => UploadedFile::fake()->create('datos.csv', 5120)], $rules)->passes());
            $this->assertTrue(Validator::make(['archivo_fiscal' => UploadedFile::fake()->create('datos.csv', 5121)], $rules)->fails());
            $this->assertTrue(Validator::make(['archivo_fiscal' => UploadedFile::fake()->create('datos.zip', 1)], $rules)->fails());
        }

        $route = new Route('PUT', 'facturas/{factura}/reparar', fn () => null);
        $route->bind(Request::create('/facturas/1/reparar', 'PUT'));
        $route->setParameter('factura', new SolicitudFactura);
        $request = new RepararSolicitudFacturaRequest;
        $request->setRouteResolver(fn () => $route);
        $rules = ['archivo' => $request->rules()['vouchers.*']];
        $this->assertTrue(Validator::make(['archivo' => UploadedFile::fake()->create('pago.pdf', 5120)], $rules)->passes());
        $this->assertTrue(Validator::make(['archivo' => UploadedFile::fake()->create('pago.pdf', 5121)], $rules)->fails());
        $this->assertTrue(Validator::make(['archivo' => UploadedFile::fake()->create('pago.zip', 1)], $rules)->fails());
    }
}
