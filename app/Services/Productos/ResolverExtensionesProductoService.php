<?php

namespace App\Services\Productos;

use App\Models\CatalogoCategoriaProducto;
use App\Models\CategoriaExtension;
use App\Models\ExtensionProducto;
use App\Models\Producto;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ResolverExtensionesProductoService
{
    /**
     * @return Collection<int, array{codigo:string,nombre:string,version:?string,origen:string,categoria_origen_id:int,configuracion_efectiva:?array}>
     */
    public function paraCategoria(?CatalogoCategoriaProducto $categoria): Collection
    {
        if (! $categoria) {
            return collect();
        }

        $globales = ExtensionProducto::query()->where('habilitada', true)->get()->keyBy('codigo');
        if ($globales->isEmpty()) {
            return collect();
        }

        $cadena = $this->cadenaAncestros($categoria);
        $asignaciones = CategoriaExtension::query()
            ->with('extension')
            ->whereIn('categoria_id', $cadena->pluck('id'))
            ->get()
            ->groupBy('categoria_id');

        $resueltas = collect();
        foreach ($globales as $codigo => $ext) {
            $resuelta = $this->resolverUna($codigo, $ext, $cadena, $asignaciones);
            if ($resuelta) {
                $resueltas->push($resuelta);
            }
        }

        return $resueltas->values();
    }

    /**
     * @return Collection<int, array{codigo:string,nombre:string,version:?string,origen:string,categoria_origen_id:int,configuracion_efectiva:?array}>
     */
    public function paraProducto(Producto $producto): Collection
    {
        $producto->loadMissing('categoria');

        return $this->paraCategoria($producto->categoria);
    }

    public function tiene(Producto $producto, string $codigo): bool
    {
        return $this->paraProducto($producto)->contains(fn (array $e) => $e['codigo'] === $codigo);
    }

    public function tieneEnCategoria(?int $categoriaId, string $codigo): bool
    {
        if (! $categoriaId) {
            return false;
        }
        $cat = CatalogoCategoriaProducto::query()->find($categoriaId);

        return $this->paraCategoria($cat)->contains(fn (array $e) => $e['codigo'] === $codigo);
    }

    public function exigir(Producto $producto, string $codigo): void
    {
        if (! $this->tiene($producto, $codigo)) {
            throw new InvalidArgumentException("La extensión «{$codigo}» no está habilitada para este producto.");
        }
    }

    public function algunaCategoriaUsa(string $codigo): bool
    {
        $ext = ExtensionProducto::query()->where('codigo', $codigo)->where('habilitada', true)->first();
        if (! $ext) {
            return false;
        }

        return CategoriaExtension::query()
            ->where('extension_id', $ext->id)
            ->where('habilitada', true)
            ->exists();
    }

    public function extensionGlobalHabilitada(string $codigo): bool
    {
        return ExtensionProducto::query()
            ->where('codigo', $codigo)
            ->where('habilitada', true)
            ->exists();
    }

    /** @return array<int|string, list<string>> categoria_id => codigos */
    public function mapaCodigosPorCategoria(): array
    {
        $categorias = CatalogoCategoriaProducto::query()->get(['id', 'parent_id', 'updated_at']);
        $out = [];
        foreach ($categorias as $cat) {
            $out[$cat->id] = $this->paraCategoria($cat)->pluck('codigo')->values()->all();
        }

        return $out;
    }

    public function invalidarCacheCategoria(?int $categoriaId = null): void
    {
        // Resolvedor sin caché persistente en v1; método conservado para callers.
    }

    /**
     * @param  Collection<int, CatalogoCategoriaProducto>  $cadena
     * @param  Collection<int|string, Collection<int, CategoriaExtension>>  $asignaciones
     * @return array{codigo:string,nombre:string,version:?string,origen:string,categoria_origen_id:int,configuracion_efectiva:?array}|null
     */
    private function resolverUna(
        string $codigo,
        ExtensionProducto $ext,
        Collection $cadena,
        Collection $asignaciones,
    ): ?array {
        foreach ($cadena as $i => $nodo) {
            $asignacion = ($asignaciones->get($nodo->id) ?? collect())
                ->first(fn (CategoriaExtension $a) => (int) $a->extension_id === (int) $ext->id);

            if (! $asignacion) {
                continue;
            }

            // Asignación directa (primer nodo = categoría del producto)
            if ($i === 0) {
                if (! $asignacion->habilitada) {
                    return null;
                }

                return $this->payload($ext, 'directa', (int) $nodo->id, $asignacion);
            }

            // Heredada: solo si heredable y habilitada
            if ($asignacion->heredable && $asignacion->habilitada) {
                return $this->payload($ext, 'heredada', (int) $nodo->id, $asignacion);
            }

            // Asignación directa deshabilitada en ancestro o no heredable: bloquea esta rama
            if (! $asignacion->habilitada) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, CatalogoCategoriaProducto> self first, then ancestors
     */
    private function cadenaAncestros(CatalogoCategoriaProducto $categoria): Collection
    {
        $cadena = collect([$categoria]);
        $actual = $categoria;
        $guard = 0;
        while ($actual->parent_id && $guard < 20) {
            $padre = CatalogoCategoriaProducto::query()->find($actual->parent_id);
            if (! $padre) {
                break;
            }
            $cadena->push($padre);
            $actual = $padre;
            $guard++;
        }

        return $cadena;
    }

    /**
     * @return array{codigo:string,nombre:string,version:?string,origen:string,categoria_origen_id:int,configuracion_efectiva:?array}
     */
    private function payload(
        ExtensionProducto $ext,
        string $origen,
        int $categoriaOrigenId,
        CategoriaExtension $asignacion,
    ): array {
        $cfgGlobal = $ext->configuracion_json;
        $cfgCat = $asignacion->configuracion_json;
        $efectiva = is_array($cfgCat) ? array_merge(is_array($cfgGlobal) ? $cfgGlobal : [], $cfgCat)
            : (is_array($cfgGlobal) ? $cfgGlobal : null);

        return [
            'codigo' => $ext->codigo,
            'nombre' => $ext->nombre,
            'version' => $ext->version,
            'origen' => $origen,
            'categoria_origen_id' => $categoriaOrigenId,
            'configuracion_efectiva' => $efectiva,
        ];
    }
}
