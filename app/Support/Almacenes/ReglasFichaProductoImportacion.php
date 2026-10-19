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
}
