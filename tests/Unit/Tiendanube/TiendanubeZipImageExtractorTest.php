<?php

namespace Tests\Unit\Tiendanube;

use App\Services\Tiendanube\TiendanubeZipImageExtractorService;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class TiendanubeZipImageExtractorTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/tnzip_'.uniqid('', true);
        mkdir($this->tmp, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rmDir($this->tmp);
        parent::tearDown();
    }

    public function test_rechaza_ruta_con_parent_directory(): void
    {
        $zipPath = $this->tmp.'/slip.zip';
        $this->makeZip($zipPath, ['../evil.jpg' => 'bytes']);
        $extract = $this->tmp.'/out';
        mkdir($extract);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ruta no permitida');

        app(TiendanubeZipImageExtractorService::class)->extraer($zipPath, $extract);
        $this->assertSame([], glob($extract.'/*') ?: []);
    }

    public function test_rechaza_exceso_de_entradas(): void
    {
        config(['tiendanube.image_import_max_entries' => 2]);
        $zipPath = $this->tmp.'/many.zip';
        $this->makeZip($zipPath, [
            'a.jpg' => '1',
            'b.jpg' => '2',
            'c.jpg' => '3',
        ]);
        $extract = $this->tmp.'/out2';
        mkdir($extract);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('máximo');

        app(TiendanubeZipImageExtractorService::class)->extraer($zipPath, $extract);
    }

    public function test_zip_corrupto(): void
    {
        $zipPath = $this->tmp.'/bad.zip';
        file_put_contents($zipPath, 'no-es-un-zip');
        $extract = $this->tmp.'/out3';
        mkdir($extract);

        $this->expectException(RuntimeException::class);

        app(TiendanubeZipImageExtractorService::class)->extraer($zipPath, $extract);
    }

    public function test_corta_archivo_que_supera_limite_real(): void
    {
        config(['tiendanube.image_import_max_file_bytes' => 20]);
        config(['tiendanube.image_import_max_total_uncompressed_bytes' => 1000]);
        $zipPath = $this->tmp.'/big.zip';
        $this->makeZip($zipPath, [
            'SKU1.jpg' => str_repeat('x', 50),
            'SKU2.jpg' => 'ok',
        ]);
        $extract = $this->tmp.'/out4';
        mkdir($extract);

        $res = app(TiendanubeZipImageExtractorService::class)->extraer($zipPath, $extract);

        $this->assertCount(1, $res['files']);
        $this->assertSame(['SKU1.jpg'], $res['oversized']);
        $this->assertSame('SKU2.jpg', $res['files'][0]['original_name']);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function makeZip(string $path, array $files): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
    }

    private function rmDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
