<?php

namespace App\Services\Productos;

use App\Models\Producto;

class GenerarFolioProductoService
{
    public function ejecutar(): int
    {
        $ultimo = Producto::query()
            ->lockForUpdate()
            ->orderByDesc('folio')
            ->value('folio');

        $numero = 100001;
        if ($ultimo && is_numeric($ultimo)) {
            $numero = (int) $ultimo + 1;
        }

        return $numero;
    }

    /**
     * Resuelve el folio a usar en importación masiva.
     * Si no hay valor en el archivo y el producto ya existe, conserva su folio.
     */
    public function folioDesdeImportacion(mixed $valorColumna, ?int $productoIdExistente = null): int
    {
        $valor = is_scalar($valorColumna) ? trim((string) $valorColumna) : '';

        if ($valor === '') {
            if ($productoIdExistente) {
                $folioActual = Producto::whereKey($productoIdExistente)->value('folio');
                if ($folioActual) {
                    return (int) $folioActual;
                }
            }

            return $this->ejecutar();
        }

        $folio = (int) preg_replace('/\D/', '', $valor);
        if ($folio <= 0) {
            if ($productoIdExistente) {
                return (int) Producto::whereKey($productoIdExistente)->value('folio');
            }

            return $this->ejecutar();
        }

        $conflicto = Producto::where('folio', $folio)
            ->when($productoIdExistente, fn ($q) => $q->where('id', '!=', $productoIdExistente))
            ->exists();

        if ($conflicto) {
            throw new \RuntimeException("El folio {$folio} ya está asignado a otro producto.");
        }

        return $folio;
    }

    public function folioDesdeFilaImportacion(array $mapping, array $row, ?int $productoIdExistente = null): int
    {
        if (empty($mapping['folio'])) {
            return $productoIdExistente
                ? (int) Producto::whereKey($productoIdExistente)->value('folio')
                : $this->ejecutar();
        }

        $columna = $mapping['folio'];
        $valor = array_key_exists($columna, $row) ? $row[$columna] : null;

        return $this->folioDesdeImportacion($valor, $productoIdExistente);
    }
}
