<?php

namespace App\Services\Medios;

use App\Contracts\Medios\AlmacenObjetosMedio;

final class AlmacenObjetosMedioFake implements AlmacenObjetosMedio
{
    /** @var array<string, array{size: int, contentType: string, uploadId?: string, parts?: array<int, string>}> */
    private array $objetos = [];

    public function urlPutSimple(string $objectKey, string $contentType, int $ttlSeg): string
    {
        $this->objetos[$objectKey] = [
            'size' => $this->objetos[$objectKey]['size'] ?? 0,
            'contentType' => $contentType,
        ];

        return 'http://medios-fake.test/put/'.rawurlencode($objectKey).'?ttl='.$ttlSeg;
    }

    public function iniciarMultipart(string $objectKey, string $contentType): string
    {
        $uploadId = 'fake-upload-'.bin2hex(random_bytes(8));
        $this->objetos[$objectKey] = [
            'size' => 0,
            'contentType' => $contentType,
            'uploadId' => $uploadId,
            'parts' => [],
        ];

        return $uploadId;
    }

    public function urlParte(string $objectKey, string $uploadId, int $partNumber, int $ttlSeg): string
    {
        return 'http://medios-fake.test/part/'.rawurlencode($objectKey).'/'.$partNumber.'?upload='.rawurlencode($uploadId).'&ttl='.$ttlSeg;
    }

    public function completarMultipart(string $objectKey, string $uploadId, array $partes): void
    {
        $this->objetos[$objectKey]['uploadId'] = $uploadId;
        $this->objetos[$objectKey]['parts'] = [];
        foreach ($partes as $parte) {
            $numero = (int) ($parte['PartNumber'] ?? 0);
            $this->objetos[$objectKey]['parts'][$numero] = (string) ($parte['ETag'] ?? '');
        }
    }

    public function abortarMultipart(string $objectKey, string $uploadId): void
    {
        unset($this->objetos[$objectKey]);
    }

    public function listarPartes(string $objectKey, string $uploadId): array
    {
        $partes = $this->objetos[$objectKey]['parts'] ?? [];
        $lista = [];
        foreach ($partes as $numero => $etag) {
            $lista[] = ['PartNumber' => (int) $numero, 'ETag' => (string) $etag];
        }

        return $lista;
    }

    public function existe(string $objectKey): bool
    {
        return array_key_exists($objectKey, $this->objetos);
    }

    public function tamano(string $objectKey): int
    {
        return (int) ($this->objetos[$objectKey]['size'] ?? 0);
    }

    public function urlLectura(string $objectKey, int $ttlSeg): string
    {
        return 'http://medios-fake.test/get/'.rawurlencode($objectKey).'?ttl='.$ttlSeg;
    }

    public function eliminar(string $objectKey): void
    {
        unset($this->objetos[$objectKey]);
    }

    public function registrarTamano(string $objectKey, int $tamano): void
    {
        if (! isset($this->objetos[$objectKey])) {
            $this->objetos[$objectKey] = ['size' => $tamano, 'contentType' => 'application/octet-stream'];
        } else {
            $this->objetos[$objectKey]['size'] = $tamano;
        }
    }
}
