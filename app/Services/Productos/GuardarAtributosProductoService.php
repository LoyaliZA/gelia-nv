<?php

namespace App\Services\Productos;

use App\Models\Atributo;
use App\Models\AtributoOpcion;
use App\Models\CategoriaAtributo;
use App\Models\Producto;
use App\Models\ProductoAtributoValor;
use App\Models\UnidadMedida;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GuardarAtributosProductoService
{
    /**
     * @param  array<int|string, mixed>  $valores  keyed by atributo_id or slug => value payload
     */
    public function sincronizar(Producto $producto, array $valores): void
    {
        $permitidos = $this->atributosPermitidos((int) $producto->categoria_id);
        $permitidosPorId = $permitidos->keyBy('id');
        $permitidosPorSlug = $permitidos->keyBy('slug');

        DB::transaction(function () use ($producto, $valores, $permitidosPorId, $permitidosPorSlug) {
            ProductoAtributoValor::query()->where('producto_id', $producto->id)->delete();

            foreach ($valores as $clave => $payload) {
                $atributo = is_numeric($clave)
                    ? $permitidosPorId->get((int) $clave)
                    : $permitidosPorSlug->get((string) $clave);

                if (! $atributo instanceof Atributo) {
                    // Si no hay categoría o el atributo no está asignado, omitir (no romper CRUD básico).
                    continue;
                }

                $filas = $this->normalizarPayload($atributo, $payload);
                foreach ($filas as $i => $fila) {
                    ProductoAtributoValor::query()->create(array_merge($fila, [
                        'producto_id' => $producto->id,
                        'atributo_id' => $atributo->id,
                        'orden' => $fila['orden'] ?? ($i + 1),
                    ]));
                }
            }
        });
    }

    /** @return \Illuminate\Support\Collection<int, Atributo> */
    public function atributosPermitidos(?int $categoriaId)
    {
        if (! $categoriaId) {
            return collect();
        }

        $ids = CategoriaAtributo::query()
            ->where('categoria_id', $categoriaId)
            ->orderBy('orden')
            ->pluck('atributo_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return Atributo::query()
            ->whereIn('id', $ids)
            ->where('estado', true)
            ->with('opciones')
            ->get()
            ->sortBy(fn (Atributo $a) => $ids->search($a->id))
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizarPayload(Atributo $atributo, mixed $payload): array
    {
        if ($payload === null || $payload === '' || $payload === []) {
            return [];
        }

        if ($atributo->tipo_dato === 'opcion') {
            $ids = is_array($payload) ? $payload : [$payload];
            if (! $atributo->permite_multiples) {
                $ids = [reset($ids)];
            }
            $out = [];
            foreach ($ids as $opcionId) {
                if ($opcionId === null || $opcionId === '') {
                    continue;
                }
                $opcion = AtributoOpcion::query()
                    ->where('atributo_id', $atributo->id)
                    ->where('id', (int) $opcionId)
                    ->first();
                if (! $opcion) {
                    throw new InvalidArgumentException("Opción inválida para atributo {$atributo->slug}.");
                }
                $out[] = ['opcion_id' => $opcion->id];
            }

            return $out;
        }

        if ($atributo->tipo_dato === 'medida') {
            $valor = is_array($payload) ? ($payload['valor'] ?? null) : $payload;
            $unidadId = is_array($payload) ? ($payload['unidad_id'] ?? null) : null;
            if ($valor === null || $valor === '') {
                return [];
            }
            if ($unidadId) {
                $unidad = UnidadMedida::query()->find((int) $unidadId);
                if (! $unidad || ($atributo->dimension_unidad && $unidad->dimension !== $atributo->dimension_unidad)) {
                    throw new InvalidArgumentException("Unidad inválida para {$atributo->slug}.");
                }
            }

            return [[
                'valor_decimal' => $valor,
                'unidad_id' => $unidadId ? (int) $unidadId : null,
            ]];
        }

        return [match ($atributo->tipo_dato) {
            'entero' => ['valor_entero' => (int) (is_array($payload) ? ($payload['valor'] ?? $payload) : $payload)],
            'decimal' => ['valor_decimal' => is_array($payload) ? ($payload['valor'] ?? $payload) : $payload],
            'booleano' => ['valor_booleano' => (bool) (is_array($payload) ? ($payload['valor'] ?? $payload) : $payload)],
            'fecha' => ['valor_fecha' => is_array($payload) ? ($payload['valor'] ?? $payload) : $payload],
            'texto_largo', 'texto' => ['valor_texto' => (string) (is_array($payload) ? ($payload['valor'] ?? $payload) : $payload)],
            default => ['valor_texto' => (string) (is_array($payload) ? json_encode($payload) : $payload)],
        }];
    }
}
