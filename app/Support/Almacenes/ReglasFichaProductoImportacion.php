<?php

namespace App\Support\Almacenes;

use Illuminate\Validation\ValidationException;

final class ReglasFichaProductoImportacion
{
    /** @var list<string> */
    public const COLUMNAS_MAPPING_OBLIGATORIAS = ['sku', 'folio', 'descripcion'];

    /**
     * @return array<string, string>
     */
    public static function reglasValidacionMapping(): array
    {
        return [
            'mapping.sku' => 'required|string',
            'mapping.folio' => 'required|string',
            'mapping.descripcion' => 'required|string',
        ];
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    public static function asegurarColumnasMapeadas(array $mapping): void
    {
        $faltantes = [];
        foreach (self::COLUMNAS_MAPPING_OBLIGATORIAS as $columna) {
            if (empty($mapping[$columna])) {
                $faltantes[] = $columna;
            }
        }

        if ($faltantes !== []) {
            throw ValidationException::withMessages([
                'mapping' => 'Mapea las columnas obligatorias: '.implode(', ', $faltantes).'.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     */
    public static function asegurarValoresEnFila(array $row, array $mapping): void
    {
        if (empty($mapping['sku']) || ! array_key_exists($mapping['sku'], $row) || trim((string) $row[$mapping['sku']]) === '') {
            throw new \RuntimeException('SKU obligatorio.');
        }

        if (empty($mapping['folio']) || ! array_key_exists($mapping['folio'], $row) || trim((string) $row[$mapping['folio']]) === '') {
            throw new \RuntimeException('Folio obligatorio.');
        }

        $folioNumerico = (int) preg_replace('/\D/', '', (string) $row[$mapping['folio']]);
        if ($folioNumerico <= 0) {
            throw new \RuntimeException('Folio obligatorio.');
        }

        if (empty($mapping['descripcion']) || ! array_key_exists($mapping['descripcion'], $row) || trim((string) $row[$mapping['descripcion']]) === '') {
            throw new \RuntimeException('Descripción obligatoria.');
        }
    }
}
