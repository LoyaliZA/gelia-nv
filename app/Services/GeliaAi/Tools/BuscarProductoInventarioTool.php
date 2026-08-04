<?php

namespace App\Services\GeliaAi\Tools;

use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use App\Services\GeliaAi\SanitizarContextoAi;

class BuscarProductoInventarioTool
{
    public function __construct(private SanitizarContextoAi $sanitizer) {}

    public function name(): string
    {
        return 'buscar_producto_inventario';
    }

    /** @return array<string, mixed> */
    public function schema(): array
    {
        $max = (int) config('gelia_ai.inventario_limit_max', 5);

        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'Busca stock de productos (pocos resultados).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'q' => ['type' => 'string', 'description' => 'SKU, nombre, barcode o folio'],
                        'almacen_id' => ['type' => 'integer', 'description' => 'Almacén opcional'],
                        'limit' => ['type' => 'integer', 'description' => "Max productos (def 3, max {$max})"],
                    ],
                    'required' => ['q'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, items?: list<array<string, mixed>>, error?: string, n?: int}
     */
    public function ejecutar(User $user, array $args): array
    {
        if (! $user->can('almacenes.productos.ver') && ! $user->can('almacenes.inventarios.ver')) {
            return ['ok' => false, 'error' => 'Sin permiso de inventario.'];
        }

        $q = trim((string) ($args['q'] ?? ''));
        if ($q === '') {
            return ['ok' => false, 'error' => 'Falta q.'];
        }

        $default = (int) config('gelia_ai.inventario_limit_default', 3);
        $max = (int) config('gelia_ai.inventario_limit_max', 5);
        $limit = max(1, min((int) ($args['limit'] ?? $default), $max));
        $almacenId = isset($args['almacen_id']) ? (int) $args['almacen_id'] : null;
        $stockMax = (int) config('gelia_ai.inventario_stock_rows_max', 3);

        // Exacto primero (SKU / barcode / folio) para no devolver LIKE amplios.
        $productos = Producto::query()
            ->where('activo', true)
            ->where(function ($w) use ($q) {
                $w->where('sku', $q)
                    ->orWhere('codigo_barras', $q);
                if (ctype_digit($q)) {
                    $w->orWhere('folio', (int) $q);
                }
            })
            ->orderBy('descripcion')
            ->limit($limit)
            ->get(['id', 'sku', 'descripcion', 'folio']);

        if ($productos->isEmpty()) {
            $productos = Producto::query()
                ->where('activo', true)
                ->buscarPorTexto($q)
                ->orderBy('descripcion')
                ->limit($limit)
                ->get(['id', 'sku', 'descripcion', 'folio']);
        }

        $items = [];
        foreach ($productos as $producto) {
            $stockQuery = Inventario::query()
                ->with(['almacen:id,codigo,nombre'])
                ->where('producto_id', $producto->id);

            if ($almacenId) {
                $stockQuery->where('almacen_id', $almacenId);
            }

            $stocks = $stockQuery->limit($stockMax)->get()->map(fn (Inventario $inv) => [
                'a' => $inv->almacen?->codigo ?: $inv->almacen?->nombre,
                'e' => (float) $inv->existencia,
                'd' => (float) $inv->disponible,
            ])->values()->all();

            $items[] = [
                'sku' => $producto->sku,
                'n' => $producto->descripcion,
                'f' => $producto->folio,
                's' => $stocks,
            ];
        }

        return $this->sanitizer->limpiar([
            'ok' => true,
            'n' => count($items),
            'items' => $items,
        ]);
    }
}
