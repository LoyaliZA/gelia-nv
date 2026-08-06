<?php

namespace App\Services\Productos;

use App\Models\FaseOlfativa;
use App\Models\NotaOlfativa;
use App\Models\Producto;
use App\Models\ProductoNotaOlfativa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuardarNotasOlfativasProductoService
{
    /**
     * @param  array<string, list<array{nota_id?:int,nombre?:string,orden?:int}|string|int>>  $porFase
     *   keyed by fase codigo: salida|corazon|fondo
     */
    public function sincronizar(Producto $producto, array $porFase): void
    {
        $fases = FaseOlfativa::query()->where('estado', true)->get()->keyBy('codigo');

        DB::transaction(function () use ($producto, $porFase, $fases) {
            ProductoNotaOlfativa::query()->where('producto_id', $producto->id)->delete();

            foreach ($porFase as $codigo => $items) {
                $fase = $fases->get($codigo);
                if (! $fase || ! is_array($items)) {
                    continue;
                }
                foreach (array_values($items) as $i => $item) {
                    $notaId = null;
                    $orden = $i + 1;
                    if (is_int($item) || (is_string($item) && ctype_digit($item))) {
                        $notaId = (int) $item;
                    } elseif (is_string($item) && trim($item) !== '') {
                        $notaId = $this->resolverOCrearNota(trim($item));
                    } elseif (is_array($item)) {
                        $notaId = isset($item['nota_id']) ? (int) $item['nota_id'] : null;
                        if (! $notaId && ! empty($item['nombre'])) {
                            $notaId = $this->resolverOCrearNota((string) $item['nombre']);
                        }
                        $orden = (int) ($item['orden'] ?? $orden);
                    }
                    if (! $notaId) {
                        continue;
                    }
                    ProductoNotaOlfativa::query()->create([
                        'producto_id' => $producto->id,
                        'nota_olfativa_id' => $notaId,
                        'fase_olfativa_id' => $fase->id,
                        'orden' => $orden,
                    ]);
                }
            }
        });
    }

    private function resolverOCrearNota(string $nombre): int
    {
        $slug = Str::slug($nombre) ?: Str::random(8);
        $nota = NotaOlfativa::query()->firstOrCreate(
            ['slug' => $slug],
            ['nombre' => $nombre, 'estado' => true]
        );

        return (int) $nota->id;
    }
}
