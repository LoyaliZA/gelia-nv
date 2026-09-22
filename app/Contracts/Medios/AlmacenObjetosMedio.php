<?php

namespace App\Contracts\Medios;

interface AlmacenObjetosMedio
{
    public function urlPutSimple(string $objectKey, string $contentType, int $ttlSeg): string;

    public function iniciarMultipart(string $objectKey, string $contentType): string;

    public function urlParte(string $objectKey, string $uploadId, int $partNumber, int $ttlSeg): string;

    /**
     * @param  list<array{PartNumber: int, ETag: string}>  $partes
     */
    public function completarMultipart(string $objectKey, string $uploadId, array $partes): void;

    public function abortarMultipart(string $objectKey, string $uploadId): void;

    /**
     * @return list<array{PartNumber: int, ETag: string}>
     */
    public function listarPartes(string $objectKey, string $uploadId): array;

    public function existe(string $objectKey): bool;

    public function tamano(string $objectKey): int;

    public function urlLectura(string $objectKey, int $ttlSeg): string;

    public function eliminar(string $objectKey): void;
}
