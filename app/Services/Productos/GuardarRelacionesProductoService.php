<?php

namespace App\Services\Productos;

use App\Models\Producto;
use App\Models\ProductoRelacion;
use Illuminate\Support\Facades\DB;

class GuardarRelacionesProductoService
{
    /**
     * @param  list<array{producto_id:int,tipo?:string,orden?:int}>  $relaciones
     */
    public function sincronizar(Producto $producto, array $relaciones, bool $simetrico = true): void
    {
        DB::transaction(function () use ($producto, $relaciones, $simetrico) {
            $existentes = ProductoRelacion::query()
                ->where('producto_id', $producto->id)
                ->get();

            foreach ($existentes as $rel) {
                if ($simetrico) {
                    ProductoRelacion::query()
                        ->where('producto_id', $rel->producto_relacionado_id)
                        ->where('producto_relacionado_id', $producto->id)
                        ->where('tipo', $rel->tipo)
                        ->delete();
                }
                $rel->delete();
            }

            foreach ($relaciones as $i => $item) {
                $otroId = (int) ($item['producto_id'] ?? 0);
                if ($otroId <= 0 || $otroId === (int) $producto->id) {
                    continue;
                }
                $tipo = (string) ($item['tipo'] ?? 'presentacion');
                $orden = isset($item['orden']) ? (int) $item['orden'] : ($i + 1);

                ProductoRelacion::query()->updateOrCreate(
                    [
                        'producto_id' => $producto->id,
                        'producto_relacionado_id' => $otroId,
                        'tipo' => $tipo,
                    ],
                    ['orden' => $orden]
                );

                if ($simetrico) {
                    ProductoRelacion::query()->updateOrCreate(
                        [
                            'producto_id' => $otroId,
                            'producto_relacionado_id' => $producto->id,
                            'tipo' => $tipo,
                        ],
                        ['orden' => $orden]
                    );
                }
            }
        });
    }
}
