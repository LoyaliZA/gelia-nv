<?php

namespace App\Services\Almacenes;

use App\Models\Producto;

class ResolverProductoPorFolioImportacionService
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     */
    public function desdeFila(array $row, array $mapping): ?int
    {
        $columna = $mapping['folio'] ?? null;
        if (! is_string($columna) || $columna === '' || ! array_key_exists($columna, $row)) {
            return null;
        }

        $valor = trim((string) $row[$columna]);
        if ($valor === '') {
            return null;
        }

        $folio = (int) preg_replace('/\D/', '', $valor);

        return $folio > 0 ? $folio : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     */
    public function exigirExistente(array $row, array $mapping): Producto
    {
        $folio = $this->desdeFila($row, $mapping);
        if ($folio === null) {
            throw new \RuntimeException('Folio obligatorio. Este caso no crea productos.');
        }

        $producto = Producto::query()->where('folio', $folio)->first();
        if (! $producto) {
            throw new \RuntimeException("El folio {$folio} no existe. Este caso no crea productos.");
        }

        return $producto;
    }
}
