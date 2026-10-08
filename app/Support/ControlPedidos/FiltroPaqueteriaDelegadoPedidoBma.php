<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use Illuminate\Support\Collection;

/**
 * Resuelve filtro multi-paquetería para la bandeja delegado.
 *
 * - Sin parámetro / array vacío: no filtrar.
 * - IDs solicitados pero ninguno válido (comercial activo): resultado vacío (no mostrar todo).
 */
class FiltroPaqueteriaDelegadoPedidoBma
{
    /**
     * @return array{aplicar: bool, ids: list<int>, forzar_vacio: bool}
     */
    public static function resolver(array $filtros): array
    {
        if (! self::tieneSeleccionSolicitada($filtros)) {
            return ['aplicar' => false, 'ids' => [], 'forzar_vacio' => false];
        }

        $solicitados = self::normalizarIds($filtros['paqueteria_ids']);
        if ($solicitados === []) {
            return ['aplicar' => false, 'ids' => [], 'forzar_vacio' => true];
        }

        $permitidos = self::idsComercialesActivos();
        $validos = array_values(array_intersect($solicitados, $permitidos));

        if ($validos === []) {
            return ['aplicar' => false, 'ids' => [], 'forzar_vacio' => true];
        }

        return ['aplicar' => true, 'ids' => $validos, 'forzar_vacio' => false];
    }

    public static function catalogoComercialActivo(): Collection
    {
        return CatalogoPaqueteriaPedido::query()
            ->where('activo', true)
            ->where('categoria', CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'categoria', 'activo']);
    }

    /** @return list<int> */
    public static function idsComercialesActivos(): array
    {
        return self::catalogoComercialActivo()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private static function tieneSeleccionSolicitada(array $filtros): bool
    {
        if (! array_key_exists('paqueteria_ids', $filtros)) {
            return false;
        }

        $raw = $filtros['paqueteria_ids'];
        if ($raw === null || $raw === '' || $raw === []) {
            return false;
        }

        if (is_string($raw)) {
            return trim($raw) !== '';
        }

        return is_array($raw);
    }

    /**
     * @return list<int>
     */
    public static function normalizarIds(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $raw),
            fn (int $id) => $id > 0
        )));
    }

}
