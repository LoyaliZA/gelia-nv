<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\Producto;
use Illuminate\Validation\ValidationException;

final class NormalizadorPiezasRegistroManualPdv
{
    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array{producto_id: int, sku: string|null, descripcion: string|null, cantidad: int, nota: string|null}>
     */
    public static function normalizar(array $filas): array
    {
        if ($filas === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map(
            static fn (array $fila): int => (int) ($fila['producto_id'] ?? 0),
            $filas
        )));
        $productos = Producto::query()
            ->whereIn('id', $ids)
            ->get(['id', 'sku', 'descripcion'])
            ->keyBy('id');

        $normalizadas = [];
        foreach ($filas as $fila) {
            $productoId = (int) ($fila['producto_id'] ?? 0);
            $producto = $productos->get($productoId);
            if ($producto === null) {
                throw ValidationException::withMessages([
                    'piezas' => 'Seleccione productos del catálogo autorizado.',
                ]);
            }

            $nota = trim((string) ($fila['nota'] ?? ''));

            $normalizadas[] = [
                'producto_id' => $producto->id,
                'sku' => $producto->sku,
                'descripcion' => $producto->descripcion,
                'cantidad' => (int) $fila['cantidad'],
                'nota' => $nota !== '' ? $nota : null,
            ];
        }

        return $normalizadas;
    }

    /**
     * @param  list<array{cantidad: int}>  $piezas
     */
    public static function cantidad(array $piezas, ?int $cantidadDeclarada = null): ?int
    {
        if ($piezas !== []) {
            return array_sum(array_column($piezas, 'cantidad'));
        }

        return $cantidadDeclarada !== null && $cantidadDeclarada > 0
            ? $cantidadDeclarada
            : null;
    }
}
