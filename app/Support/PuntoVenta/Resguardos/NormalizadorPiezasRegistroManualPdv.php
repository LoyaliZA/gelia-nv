<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\Producto;
use Illuminate\Validation\ValidationException;

final class NormalizadorPiezasRegistroManualPdv
{
    public const ESTADOS = ['bueno', 'regular', 'malo', 'danado', 'sin_existencia'];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array{producto_id: int, sku: string|null, descripcion: string|null, cantidad: int, nota: string|null, estado_fisico: string, unica_pieza: bool, mejor_ejemplar: bool, evidencia_ids: list<int>}>
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
        foreach ($filas as $indice => $fila) {
            $productoId = (int) ($fila['producto_id'] ?? 0);
            $producto = $productos->get($productoId);
            if ($producto === null) {
                throw ValidationException::withMessages([
                    'piezas' => 'Seleccione productos del catálogo autorizado.',
                ]);
            }

            $nota = trim((string) ($fila['nota'] ?? ''));
            $estado = (string) ($fila['estado_fisico'] ?? 'bueno');
            if (! in_array($estado, self::ESTADOS, true)) {
                $estado = 'bueno';
            }
            if (self::requiereObservacion($estado) && $nota === '') {
                throw ValidationException::withMessages([
                    "piezas.{$indice}.nota" => 'Esta pieza requiere una observación.',
                ]);
            }

            $evidenciaIds = [];
            foreach (is_array($fila['evidencia_ids'] ?? null) ? $fila['evidencia_ids'] : [] as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $evidenciaIds[] = $id;
                }
            }

            $normalizadas[] = [
                'producto_id' => $producto->id,
                'sku' => $producto->sku,
                'descripcion' => $producto->descripcion,
                'cantidad' => (int) $fila['cantidad'],
                'nota' => $nota !== '' ? $nota : null,
                'estado_fisico' => $estado,
                'unica_pieza' => filter_var($fila['unica_pieza'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'mejor_ejemplar' => filter_var($fila['mejor_ejemplar'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'evidencia_ids' => array_values(array_unique($evidenciaIds)),
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

    public static function requiereObservacion(string $estado): bool
    {
        return in_array($estado, ['malo', 'danado', 'sin_existencia'], true);
    }

    public static function requiereEvidencia(string $estado): bool
    {
        return in_array($estado, ['malo', 'danado'], true);
    }
}
