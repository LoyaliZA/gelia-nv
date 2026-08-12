<?php

namespace App\Services\GeliaAi\Tools;

use App\Models\Producto;
use App\Models\ProductoCosto;
use App\Models\User;
use App\Services\GeliaAi\SanitizarContextoAi;
use App\Services\Productos\ArmarFichaProductoService;

class BuscarProductoInventarioTool
{
    public function __construct(
        private SanitizarContextoAi $sanitizer,
        private ArmarFichaProductoService $ficha,
    ) {}

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
                'description' => 'Busca producto por SKU/nombre: stock multi-almacén, ficha (atributos/extensiones), relacionados y ventas. No inventa datos.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'q' => ['type' => 'string', 'description' => 'SKU, nombre, barcode o folio'],
                        'almacen_id' => ['type' => 'integer', 'description' => 'Almacén de contexto (PDV) opcional'],
                        'limit' => ['type' => 'integer', 'description' => "Max productos (def 3, max {$max})"],
                        'con_precios' => ['type' => 'boolean', 'description' => 'Incluir costo/precio si el usuario tiene permiso'],
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
        if (
            ! $user->can('almacenes.productos.ver')
            && ! $user->can('gestion_interna.productos.ver')
            && ! $user->can('almacenes.inventarios.ver')
        ) {
            return ['ok' => false, 'error' => 'Sin permiso de inventario.'];
        }

        $q = $this->limpiarConsulta((string) ($args['q'] ?? ''));
        if ($q === '') {
            return ['ok' => false, 'error' => 'Falta q.'];
        }

        $default = (int) config('gelia_ai.inventario_limit_default', 3);
        $max = (int) config('gelia_ai.inventario_limit_max', 5);
        $limit = max(1, min((int) ($args['limit'] ?? $default), $max));
        $almacenId = isset($args['almacen_id']) ? (int) $args['almacen_id'] : null;
        $stockMax = max(3, (int) config('gelia_ai.inventario_stock_rows_max', 3));
        $conPrecios = ! empty($args['con_precios']) && $user->can('almacenes.costos.ver');

        $exacto = true;
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
            ->get();

        if ($productos->isEmpty()) {
            $exacto = false;
            $productos = Producto::query()
                ->where('activo', true)
                ->buscarPorTexto($q)
                ->orderBy('descripcion')
                ->limit($limit)
                ->get();
        }

        $items = [];
        foreach ($productos as $producto) {
            $ficha = $this->ficha->paraProducto($producto, $almacenId, $stockMax);
            $item = [
                'sku' => $ficha['sku'],
                'n' => $ficha['nombre'],
                'f' => $producto->folio,
                's' => $ficha['stock'],
                'attrs' => $ficha['atributos'],
                'ext' => $ficha['extensiones'] ?? (object) [],
                'rel' => $ficha['relacionados'],
                'cont' => $ficha['contenido'],
                'ven' => $ficha['ventas'],
            ];

            if ($conPrecios) {
                $costosQuery = ProductoCosto::query()
                    ->with(['almacen:id,codigo,nombre'])
                    ->where('producto_id', $producto->id);
                if ($almacenId) {
                    $costosQuery->where('almacen_id', $almacenId);
                }
                $costosRows = $costosQuery->limit(3)->get();
                $item['p'] = $costosRows->map(fn (ProductoCosto $c) => [
                    'a' => $c->almacen?->codigo ?: $c->almacen?->nombre,
                    'co' => (float) $c->costo,
                    'pv' => $c->precio_venta !== null ? (float) $c->precio_venta : null,
                ])->values()->all();
            }

            $items[] = $item;
        }

        $n = count($items);
        $payload = [
            'ok' => true,
            'n' => $n,
            'exacto' => $exacto && $n > 0,
            'sugerir' => $n > 0 && ! $exacto,
            'con_precios' => $conPrecios,
            'aviso' => 'No inventes attrs/notas/rel/ven/stock ausentes. Si falta ficha dilo.',
            'items' => $items,
        ];
        if ($conPrecios && $n > 0) {
            $conFilasPrecio = collect($items)->contains(fn (array $it) => ($it['p'] ?? []) !== []);
            if (! $conFilasPrecio) {
                $payload['aviso_precios'] = 'Sin registros en Almacenes→Costos para estos productos.';
            }
        }

        return $this->sanitizer->limpiar($payload);
    }

    private function limpiarConsulta(string $q): string
    {
        $q = trim($q);
        if ($q === '') {
            return '';
        }

        $q = preg_replace(
            '/\b(cu[aá]nt[oa]s?|quedan?|hay|stock|inventario|existencia|existencias|perfume|perfumes|unidades?|producto|productos|revisa|busca|buscar|precio|precios|costo|costos|cuesta|vale|puedes?|podr[ií]as?|darme|dame|dime|necesito|quiero|mostrar|muestra|informa(?:ci[oó]n)?|info|datos?|detalle|detalles|actual(?:es)?|disponible|disponibles|sobre|favor|por\s+favor|del?|la|el|los|las|un|una)\b/iu',
            ' ',
            $q
        ) ?? $q;

        return trim(preg_replace('/\s+/u', ' ', $q) ?? $q);
    }
}
