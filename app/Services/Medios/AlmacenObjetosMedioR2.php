<?php

namespace App\Services\Medios;

use App\Contracts\Medios\AlmacenObjetosMedio;
use Aws\S3\S3Client;
use RuntimeException;

final class AlmacenObjetosMedioR2 implements AlmacenObjetosMedio
{
    public function __construct(
        private readonly ?S3Client $cliente = null,
        private readonly ?string $bucket = null,
        private readonly ?string $urlPublica = null,
    ) {}

    public function urlPutSimple(string $objectKey, string $contentType, int $ttlSeg): string
    {
        $cmd = $this->cliente()->getCommand('PutObject', [
            'Bucket' => $this->bucket(),
            'Key' => $objectKey,
            'ContentType' => $contentType,
        ]);

        return (string) $this->cliente()->createPresignedRequest($cmd, '+'.$ttlSeg.' seconds')->getUri();
    }

    public function iniciarMultipart(string $objectKey, string $contentType): string
    {
        $resultado = $this->cliente()->createMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $objectKey,
            'ContentType' => $contentType,
        ]);

        return (string) ($resultado['UploadId'] ?? '');
    }

    public function urlParte(string $objectKey, string $uploadId, int $partNumber, int $ttlSeg): string
    {
        $cmd = $this->cliente()->getCommand('UploadPart', [
            'Bucket' => $this->bucket(),
            'Key' => $objectKey,
            'UploadId' => $uploadId,
            'PartNumber' => $partNumber,
        ]);

        return (string) $this->cliente()->createPresignedRequest($cmd, '+'.$ttlSeg.' seconds')->getUri();
    }

    public function completarMultipart(string $objectKey, string $uploadId, array $partes): void
    {
        $this->cliente()->completeMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $objectKey,
            'UploadId' => $uploadId,
            'MultipartUpload' => ['Parts' => $partes],
        ]);
    }

    public function abortarMultipart(string $objectKey, string $uploadId): void
    {
        $this->cliente()->abortMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $objectKey,
            'UploadId' => $uploadId,
        ]);
    }

    public function listarPartes(string $objectKey, string $uploadId): array
    {
        $resultado = $this->cliente()->listParts([
            'Bucket' => $this->bucket(),
            'Key' => $objectKey,
            'UploadId' => $uploadId,
        ]);

        $lista = [];
        foreach ($resultado['Parts'] ?? [] as $parte) {
            $lista[] = [
                'PartNumber' => (int) ($parte['PartNumber'] ?? 0),
                'ETag' => (string) ($parte['ETag'] ?? ''),
            ];
        }

        return $lista;
    }

    public function existe(string $objectKey): bool
    {
        return $this->cliente()->doesObjectExist($this->bucket(), $objectKey);
    }

    public function tamano(string $objectKey): int
    {
        $head = $this->cliente()->headObject([
            'Bucket' => $this->bucket(),
            'Key' => $objectKey,
        ]);

        return (int) ($head['ContentLength'] ?? 0);
    }

    public function urlLectura(string $objectKey, int $ttlSeg): string
    {
        $base = $this->urlPublica();
        if ($base !== '') {
            return $base.'/'.ltrim($objectKey, '/');
        }

        $cmd = $this->cliente()->getCommand('GetObject', [
            'Bucket' => $this->bucket(),
            'Key' => $objectKey,
        ]);

        return (string) $this->cliente()->createPresignedRequest($cmd, '+'.$ttlSeg.' seconds')->getUri();
    }

    public function eliminar(string $objectKey): void
    {
        $this->cliente()->deleteObject([
            'Bucket' => $this->bucket(),
            'Key' => $objectKey,
        ]);
    }

    private function cliente(): S3Client
    {
        if ($this->cliente instanceof S3Client) {
            return $this->cliente;
        }

        $disk = config('filesystems.disks.'.config('medios.disk', 'r2'), []);
        if (! class_exists(S3Client::class)) {
            throw new RuntimeException('Falta el SDK S3 para Cloudflare R2.');
        }

        return new S3Client([
            'version' => 'latest',
            'region' => $disk['region'] ?? 'auto',
            'endpoint' => $disk['endpoint'] ?? null,
            'use_path_style_endpoint' => (bool) ($disk['use_path_style_endpoint'] ?? false),
            'credentials' => [
                'key' => $disk['key'] ?? '',
                'secret' => $disk['secret'] ?? '',
            ],
        ]);
    }

    private function bucket(): string
    {
        if (is_string($this->bucket) && $this->bucket !== '') {
            return $this->bucket;
        }

        return (string) (config('filesystems.disks.'.config('medios.disk', 'r2').'.bucket') ?? '');
    }

    private function urlPublica(): string
    {
        if (is_string($this->urlPublica)) {
            return rtrim($this->urlPublica, '/');
        }

        return rtrim((string) (config('filesystems.disks.'.config('medios.disk', 'r2').'.url') ?? ''), '/');
    }
}
