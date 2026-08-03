<?php

namespace Tests\Unit\Tiendanube;

use App\Services\Tiendanube\OptimizarImagenTiendanubeService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OptimizarImagenTiendanubeServiceTest extends TestCase
{
    public function test_flags_desde_dimensiones(): void
    {
        $this->assertSame(
            ['alerta_pequena' => true, 'alerta_no_cuadrada' => false, 'requiere_revision' => true],
            OptimizarImagenTiendanubeService::flagsDesdeDimensiones(600, 600)
        );

        $this->assertSame(
            ['alerta_pequena' => false, 'alerta_no_cuadrada' => false, 'requiere_revision' => false],
            OptimizarImagenTiendanubeService::flagsDesdeDimensiones(1280, 1280)
        );

        $this->assertSame(
            ['alerta_pequena' => false, 'alerta_no_cuadrada' => true, 'requiere_revision' => true],
            OptimizarImagenTiendanubeService::flagsDesdeDimensiones(1280, 2250)
        );

        $this->assertSame(
            ['alerta_pequena' => true, 'alerta_no_cuadrada' => true, 'requiere_revision' => true],
            OptimizarImagenTiendanubeService::flagsDesdeDimensiones(500, 900)
        );

        $this->assertSame(
            ['alerta_pequena' => false, 'alerta_no_cuadrada' => false, 'requiere_revision' => false],
            OptimizarImagenTiendanubeService::flagsDesdeDimensiones(null, null)
        );
    }

    public function test_imagen_pequena_sin_reescalar_marca_alerta(): void
    {
        $path = $this->escribirPng1x1();
        $file = new UploadedFile($path, 'tiny.png', 'image/png', null, true);

        $opt = app(OptimizarImagenTiendanubeService::class)->ejecutar($file);

        $this->assertTrue($opt['alerta_pequena']);
        $this->assertFalse($opt['alerta_no_cuadrada']);
        $this->assertTrue($opt['requiere_revision']);
        $this->assertSame(1, $opt['width']);
        $this->assertSame(1, $opt['height']);
        // Sin resize: output = original (aunque haya WebP, las dims de salida siguen 1x1)
        $this->assertSame(1, $opt['output_width']);
        $this->assertSame(1, $opt['output_height']);

        if ($opt['cleanup'] && is_file($opt['path'])) {
            @unlink($opt['path']);
        }
        @unlink($path);
    }

    public function test_cuadrada_grande_a_1280_webp(): void
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD+WebP requerido');
        }

        $path = $this->escribirJpegGd(2000, 2000);
        $file = new UploadedFile($path, 'grande.jpg', 'image/jpeg', null, true);

        $opt = app(OptimizarImagenTiendanubeService::class)->ejecutar($file);

        $this->assertFalse($opt['requiere_revision']);
        $this->assertSame(2000, $opt['width']);
        $this->assertSame(2000, $opt['height']);
        $this->assertSame(1280, $opt['output_width']);
        $this->assertSame(1280, $opt['output_height']);
        $this->assertStringEndsWith('.webp', $opt['filename']);
        $this->assertTrue($opt['cleanup']);
        $this->assertFileExists($opt['path']);

        @unlink($opt['path']);
        @unlink($path);
    }

    public function test_rectangular_reescala_lado_menor_1280_y_alerta(): void
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD+WebP requerido');
        }

        $path = $this->escribirJpegGd(900, 1600);
        $file = new UploadedFile($path, 'rect.jpg', 'image/jpeg', null, true);

        $opt = app(OptimizarImagenTiendanubeService::class)->ejecutar($file);

        $this->assertTrue($opt['alerta_no_cuadrada']);
        $this->assertTrue($opt['requiere_revision']);
        $this->assertFalse($opt['alerta_pequena']);
        $this->assertSame(1280, $opt['output_width']);
        $this->assertSame((int) round(1600 * (1280 / 900)), $opt['output_height']);
        $this->assertStringEndsWith('.webp', $opt['filename']);

        if ($opt['cleanup']) {
            @unlink($opt['path']);
        }
        @unlink($path);
    }

    private function escribirPng1x1(): string
    {
        $bin = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );
        $path = sys_get_temp_dir().'/tn_test_'.uniqid('', true).'.png';
        file_put_contents($path, $bin);

        return $path;
    }

    private function escribirJpegGd(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, 200, 100, 50);
        imagefilledrectangle($img, 0, 0, $w, $h, $bg);
        $path = sys_get_temp_dir().'/tn_test_'.uniqid('', true).'.jpg';
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return $path;
    }
}
