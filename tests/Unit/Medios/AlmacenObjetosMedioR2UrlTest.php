<?php

namespace Tests\Unit\Medios;

use App\Services\Medios\AlmacenObjetosMedioR2;
use Aws\S3\S3Client;
use Tests\TestCase;

class AlmacenObjetosMedioR2UrlTest extends TestCase
{
    public function test_endpoint_api_firma_la_lectura(): void
    {
        config([
            'medios.disk' => 'r2',
            'filesystems.disks.r2.endpoint' => 'https://acct.r2.cloudflarestorage.com',
        ]);

        $url = $this->almacen('https://acct.r2.cloudflarestorage.com')
            ->urlLectura('advertising/media/a.png', 60);

        $this->assertStringContainsString('X-Amz-Signature', $url);
        $this->assertStringContainsString('advertising/media/a.png', $url);
    }

    public function test_dominio_publico_distinto_queda_sin_firma(): void
    {
        config([
            'medios.disk' => 'r2',
            'filesystems.disks.r2.endpoint' => 'https://acct.r2.cloudflarestorage.com',
        ]);

        $url = $this->almacen('https://media.example.com')
            ->urlLectura('advertising/media/a.png', 60);

        $this->assertSame('https://media.example.com/advertising/media/a.png', $url);
    }

    private function almacen(string $urlPublica): AlmacenObjetosMedioR2
    {
        $cliente = new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => 'https://acct.r2.cloudflarestorage.com',
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
        ]);

        return new AlmacenObjetosMedioR2($cliente, 'gelia-media', $urlPublica);
    }
}
