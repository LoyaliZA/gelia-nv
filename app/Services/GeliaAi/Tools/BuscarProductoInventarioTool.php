<?php

namespace App\Services\GeliaAi\Tools;

use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoCosto;
use App\Models\User;
use App\Services\GeliaAi\ResolverAlmacenGeliaAi;
use App\Services\GeliaAi\SanitizarContextoAi;
use App\Services\Productos\ArmarFichaProductoService;

class BuscarProductoInventarioTool implements HerramientaGeliaAi
{
    public function __construct(
        private SanitizarContextoAi $sanitizer,
        private ArmarFichaProductoService $ficha,
        private ResolverAlmacenGeliaAi $almacenes,
    ) {}

    public function name(): string
    {
        return 'buscar_producto_inventario';
    }

    /** @return array<string, mixed> */
    public function schema(): array
    {
        $max = (int) config('gelia_ai.inventario_limit_max', 10);

        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'Consulta productos activos por SKU, código de barras, folio o nombre/variante y devuelve existencias por almacén dentro del alcance de la persona usuaria, ficha, y precios si tiene permiso. Para continuar una consulta usa el SKU ya identificado.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'q' => ['type' => 'string', 'maxLength' => 120, 'description' => 'SKU, código de barras, folio o nombre del producto (sin frases de cortesía)'],
                        'almacen' => ['type' => 'string', 'maxLength' => 60, 'description' => 'Código o nombre del almacén para filtrar (opcional)'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => $max, 'description' => "Máximo de productos (def 5, máx {$max})"],
                        'pagina' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'Página de resultados para coincidencias parciales'],
                        'con_precios' => ['type' => 'boolean', 'description' => 'Incluir costo/precio de referencia (solo si la persona usuaria tiene permiso)'],
                    ],
                    'required' => ['q'],
                ],
            ],
        ];
    }

    public function disponiblePara(User $user): bool
    {
        return $user->can('almacenes.productos.ver')
            || $user->can('gestion_interna.productos.ver')
            || $user->can('almacenes.inventarios.ver');
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function ejecutar(User $user, array $args, array $archivos = []): array
    {
        if (! $this->disponiblePara($user)) {
            return ['ok' => false, 'error_code' => 'sin_permiso', 'error' => 'Sin permiso para consultar productos o inventario.'];
        }

        $q = $this->limpiarConsulta((string) ($args['q'] ?? ''));
        if ($q === '') {
            return ['ok' => false, 'error_code' => 'argumento_invalido', 'error' => 'Falta q (SKU, código o nombre del producto).'];
        }

        $default = (int) config('gelia_ai.inventario_limit_default', 5);
        $max = (int) config('gelia_ai.inventario_limit_max', 10);
        $limit = max(1, min((int) ($args['limit'] ?? $default), $max));
        $pagina = max(1, (int) ($args['pagina'] ?? 1));
        $stockMax = max(3, (int) config('gelia_ai.inventario_stock_rows_max', 8));
        $idsPermitidos = $this->almacenes->idsOperables($user);

        $almacen = null;
        $almacenArg = $args['almacen'] ?? $args['almacen_id'] ?? null;
        if ($almacenArg !== null && $almacenArg !== '') {
            $res = $this->almacenes->resolverParaUsuario(
                $user,
                is_int($almacenArg) ? $almacenArg : null,
                is_int($almacenArg) ? null : (string) $almacenArg,
            );
            if (! $res['ok']) {
                return $res;
            }
            $almacen = $res['almacen'];
        }

        $conPrecios = ! empty($args['con_precios']) && $user->can('almacenes.costos.ver');
        $conVentas = $user->can('reportes.ventas.ver');

        $coincidencia = 'exacta';
        $productos = Producto::query()
            ->where('activo', true)
            ->where(function ($w) use ($q) {
                $w->where('sku', $q)
                    ->orWhere('sku', Producto::normalizarSku($q))
                    ->orWhere('codigo_barras', $q);
                if (ctype_digit($q) && strlen($q) <= 9) {
                    $w->orWhere('folio', (int) $q);
                }
            })
            ->orderBy('descripcion')
            ->limit($limit + 1)
            ->get();

        if ($productos->isEmpty()) {
            $coincidencia = 'parcial';
            $productos = Producto::query()
                ->where('activo', true)
                ->buscarPorTexto($q)
                ->orderBy('descripcion')
                ->offset(($pagina - 1) * $limit)
                ->limit($limit + 1)
                ->get();
        }

        $hayMas = $productos->count() > $limit;
        $productos = $productos->take($limit);

        $items = [];
        foreach ($productos as $producto) {
            $items[] = $this->item($producto, $almacen, $idsPermitidos, $stockMax, $conPrecios, $conVentas);
        }

        $n = count($items);
        if ($n === 0) {
            $coincidencia = 'ninguna';
        }

        $payload = [
            'ok' => true,
            'consultado_en' => now()->toIso8601String(),
            'q' => $q,
            'coincidencia' => $coincidencia,
            'n' => $n,
            'pagina' => $coincidencia === 'parcial' ? $pagina : 1,
            'hay_mas' => $hayMas,
            'requiere_eleccion' => $n > 1,
            'almacen_filtro' => $almacen ? ['codigo' => $almacen->codigo, 'nombre' => $almacen->nombre] : null,
            'almacenes_en_alcance' => count($idsPermitidos),
            'con_precios' => $conPrecios,
            'items' => $items,
        ];

        if ($idsPermitidos === []) {
            $payload['aviso_alcance'] = 'La persona usuaria no tiene sucursales operables asignadas: no se puede mostrar stock (falta de permisos, no ausencia de datos).';
        }
        if ($hayMas) {
            $payload['aviso_paginacion'] = 'Hay más coincidencias que las mostradas; no presentes esta lista como completa. Pide más detalle o usa pagina+1.';
        }
        if (! empty($args['con_precios']) && ! $conPrecios) {
            $payload['aviso_precios'] = 'Sin permiso para ver costos/precios (almacenes.costos.ver).';
        } elseif ($conPrecios && $n > 0 && ! collect($items)->contains(fn (array $it) => ($it['p'] ?? []) !== [])) {
            $payload['aviso_precios'] = 'Sin registros en Almacenes → Costos para estos productos en tus almacenes.';
        }

        return $this->sanitizer->limpiar($payload);
    }

    /**
     * @param  list<int>  $idsPermitidos
     * @return array<string, mixed>
     */
    private function item(Producto $producto, ?Almacen $almacen, array $idsPermitidos, int $stockMax, bool $conPrecios, bool $conVentas): array
    {
        $alcance = $almacen ? [(int) $almacen->id] : $idsPermitidos;
        $ficha = $this->ficha->paraProducto($producto, $almacen?->id, $stockMax, $alcance);

        $total = Inventario::query()
            ->where('producto_id', $producto->id)
            ->whereIn('almacen_id', $alcance === [] ? [0] : $alcance)
            ->selectRaw('COUNT(*) as registros, COALESCE(SUM(existencia),0) as e, COALESCE(SUM(apartado),0) as ap, COALESCE(SUM(transito_oc),0) as toc, COALESCE(SUM(transito_ot),0) as tot')
            ->first();

        $registros = (int) ($total->registros ?? 0);
        $filas = $ficha['stock'];

        $item = [
            'sku' => $ficha['sku'],
            'n' => $ficha['nombre'],
            'f' => $producto->folio,
            'marca' => $ficha['marca'],
            'stock' => [
                'almacenes_con_registro' => $registros,
                'mostrados' => count($filas),
                'total' => [
                    'e' => (float) ($total->e ?? 0),
                    'ap' => (float) ($total->ap ?? 0),
                    'd' => round((float) ($total->e ?? 0) - (float) ($total->ap ?? 0), 3),
                    'toc' => (float) ($total->toc ?? 0),
                    'tot' => (float) ($total->tot ?? 0),
                ],
                'filas' => $filas,
            ],
            'attrs' => $ficha['atributos'],
            'ext' => $ficha['extensiones'] ?: (object) [],
            'rel' => $ficha['relacionados'],
            'cont' => $ficha['contenido'] ? array_filter([
                'pitch_venta' => $ficha['contenido']['pitch_venta'] ?? null,
                'descripcion_corta' => $ficha['contenido']['descripcion_corta'] ?? null,
            ]) : null,
        ];

        if ($conVentas) {
            $item['ven'] = $ficha['ventas'];
        }

        if ($conPrecios) {
            $item['p'] = ProductoCosto::query()
                ->with(['almacen:id,codigo,nombre'])
                ->where('producto_id', $producto->id)
                ->whereIn('almacen_id', $alcance === [] ? [0] : $alcance)
                ->limit(5)
                ->get()
                ->map(fn (ProductoCosto $c) => [
                    'a' => $c->almacen?->codigo ?: $c->almacen?->nombre,
                    'co' => (float) $c->costo,
                    'pv' => $c->precio_venta !== null ? (float) $c->precio_venta : null,
                ])->values()->all();
        }

        return $item;
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
