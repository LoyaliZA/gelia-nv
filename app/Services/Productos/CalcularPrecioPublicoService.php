<?php

namespace App\Services\Productos;

use App\Models\Producto;
use InvalidArgumentException;

class CalcularPrecioPublicoService
{
    /**
     * Precio de Público General: costo promedio × (1 + margen / 100).
     * El costo de reposición no entra. Sin costo o sin margen el precio queda vacío.
     */
    public function calcular(mixed $costo, mixed $margen): ?string
    {
        if ($margen !== null && $margen !== '') {
            $margenNumero = round((float) $margen, 2);
            if ($margenNumero < 0 || $margenNumero > 100) {
                throw new InvalidArgumentException('El margen debe estar entre 0 y 100.');
            }
        }

        if ($costo === null || $costo === '' || $margen === null || $margen === '') {
            return null;
        }

        return number_format(round((float) $costo * (1 + (float) $margen / 100), 2), 2, '.', '');
    }

    /**
     * Margen que reproduce el precio capturado, si cabe entre 0 y 100.
     */
    public function margenDesdePrecio(mixed $costo, mixed $precio): ?string
    {
        if ($costo === null || $costo === '' || $precio === null || $precio === '') {
            return null;
        }

        if ((float) $costo <= 0) {
            return null;
        }

        $margen = round((((float) $precio / (float) $costo) - 1) * 100, 2);
        if ($margen < 0 || $margen > 100) {
            return null;
        }

        return number_format($margen, 2, '.', '');
    }

    public function aplicar(Producto $producto): void
    {
        $producto->load('costos');
        $producto->precio_publico = $this->calcular($producto->costos?->costo, $producto->margen);
        $producto->save();
    }

    /**
     * Conserva la fila de CEDIS; si no hay, la primera con costo distinto de cero; si no, la de menor id.
     *
     * @param  iterable<object{id: int|string, costo: mixed, codigo?: string|null, nombre?: string|null}>  $filas
     */
    public function elegirFilaCosto(iterable $filas): ?object
    {
        $lista = collect($filas)->sortBy(fn ($fila) => (int) $fila->id)->values();
        if ($lista->isEmpty()) {
            return null;
        }

        $cedis = $lista->first(function ($fila) {
            $texto = mb_strtoupper(trim((string) ($fila->codigo ?? '').' '.(string) ($fila->nombre ?? '')), 'UTF-8');

            return str_contains($texto, 'CEDIS');
        });
        if ($cedis !== null) {
            return $cedis;
        }

        $conCosto = $lista->first(fn ($fila) => (float) $fila->costo != 0);

        return $conCosto ?? $lista->first();
    }
}
