<?php

namespace App\Services\Tiendanube\Precios;

final class TiendanubePrecioCatalogoFiltros
{
    public const PROMOCION_CUALQUIERA = 'cualquiera';

    public const PROMOCION_CON = 'con';

    public const PROMOCION_SIN = 'sin';

    public const COSTO_CUALQUIERA = 'cualquiera';

    public const COSTO_CON = 'con';

    public const COSTO_SIN = 'sin';

    /**
     * @param  list<int>  $categoriaIds
     */
    public function __construct(
        public readonly string $q = '',
        public readonly array $categoriaIds = [],
        public readonly bool $incluirSubcategorias = false,
        public readonly bool $sinCategoria = false,
        public readonly ?string $precioMin = null,
        public readonly ?string $precioMax = null,
        public readonly string $promocion = self::PROMOCION_CUALQUIERA,
        public readonly string $costo = self::COSTO_CUALQUIERA,
        public readonly string $sort = 'id',
        public readonly string $dir = 'asc',
        public readonly int $page = 1,
        public readonly int $perPage = 25,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function fromArray(array $datos): self
    {
        $categoriaIds = $datos['categoria_ids'] ?? [];
        if (! is_array($categoriaIds)) {
            $categoriaIds = [];
        }
        $categoriaIds = array_values(array_unique(array_map('intval', $categoriaIds)));
        sort($categoriaIds);

        $q = trim((string) ($datos['q'] ?? ''));
        $dir = strtolower((string) ($datos['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $sort = (string) ($datos['sort'] ?? 'id');
        if (! in_array($sort, ['id', 'sku', 'precio_normal'], true)) {
            $sort = 'id';
        }

        $promocion = (string) ($datos['promocion'] ?? self::PROMOCION_CUALQUIERA);
        if (! in_array($promocion, [self::PROMOCION_CUALQUIERA, self::PROMOCION_CON, self::PROMOCION_SIN], true)) {
            $promocion = self::PROMOCION_CUALQUIERA;
        }

        $costo = (string) ($datos['costo'] ?? self::COSTO_CUALQUIERA);
        if (! in_array($costo, [self::COSTO_CUALQUIERA, self::COSTO_CON, self::COSTO_SIN], true)) {
            $costo = self::COSTO_CUALQUIERA;
        }

        $page = max(1, (int) ($datos['page'] ?? 1));
        $perPage = (int) ($datos['per_page'] ?? 25);
        if ($perPage < 1) {
            $perPage = 25;
        }
        if ($perPage > 50) {
            $perPage = 50;
        }

        return new self(
            q: $q,
            categoriaIds: $categoriaIds,
            incluirSubcategorias: (bool) ($datos['incluir_subcategorias'] ?? false),
            sinCategoria: (bool) ($datos['sin_categoria'] ?? false),
            precioMin: self::nullableDecimal($datos['precio_min'] ?? null),
            precioMax: self::nullableDecimal($datos['precio_max'] ?? null),
            promocion: $promocion,
            costo: $costo,
            sort: $sort,
            dir: $dir,
            page: $page,
            perPage: $perPage,
        );
    }

    /**
     * Filtros que definen el universo; no incluyen paginación ni orden.
     *
     * @return array<string, mixed>
     */
    public function universo(): array
    {
        return [
            'q' => $this->q,
            'categoria_ids' => $this->categoriaIds,
            'incluir_subcategorias' => $this->incluirSubcategorias,
            'sin_categoria' => $this->sinCategoria,
            'precio_min' => $this->precioMin,
            'precio_max' => $this->precioMax,
            'promocion' => $this->promocion,
            'costo' => $this->costo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->universo() + [
            'sort' => $this->sort,
            'dir' => $this->dir,
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }

    public function equivaleUniverso(array $otros): bool
    {
        $normalizados = self::fromArray($otros)->universo();

        return $normalizados === $this->universo();
    }

    private static function nullableDecimal(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return TiendanubePrecioImporte::formatStored($valor);
    }
}
