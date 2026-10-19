<?php

namespace App\Services\Almacenes;

use App\Models\Producto;
use App\Services\Productos\GenerarFolioProductoService;
use Illuminate\Support\Str;

class ProcesarFilaProductoImportacionService
{
    public function __construct(
        private readonly NormalizarTextoImportacionService $normalizador,
        private readonly ResolverRelacionCatalogoService $resolverCatalogo,
        private readonly GenerarFolioProductoService $generarFolio,
        private readonly ResolverProductoPorFolioImportacionService $folios,
    ) {}

    /**
     * @return array{accion: string, producto: Producto}
     */
    public function ejecutar(array $row, array $mapping): array
    {
        $sku = $this->skuEnFila($row, $mapping);
        $folio = $this->folios->desdeFila($row, $mapping);
        $productoExistente = $folio ? Producto::query()->where('folio', $folio)->first() : null;

        if ($productoExistente) {
            return $this->actualizar($productoExistente, $row, $mapping, $sku);
        }

        return $this->crear($row, $mapping, $sku, $folio);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     * @return array{accion: string, producto: Producto}
     */
    private function actualizar(Producto $producto, array $row, array $mapping, string $sku): array
    {
        $datos = [];

        if ($sku !== '' && $producto->sku !== $sku) {
            $this->asegurarSkuLibre($sku, $producto->id);
            $datos['sku'] = $sku;
        }

        $descripcion = $this->textoOpcional($row, $mapping, 'descripcion');
        if ($descripcion !== null) {
            $datos['descripcion'] = $descripcion;
        }

        $categoriaId = $this->relacionOpcional($row, $mapping, 'categoria', 'categoriaId');
        if ($categoriaId !== null) {
            $datos['categoria_id'] = $categoriaId;
        }

        $marcaId = $this->relacionOpcional($row, $mapping, 'marca', 'marcaId');
        if ($marcaId !== null) {
            $datos['marca_id'] = $marcaId;
        }

        if ($this->celdaConValor($row, $mapping['codigo_barras'] ?? null)) {
            $datos['codigo_barras'] = $this->normalizador->codigoBarras(
                $row[$mapping['codigo_barras']] ?? null,
                $sku !== '' ? $sku : $producto->sku,
            );
        }

        if ($this->celdaConValor($row, $mapping['peso'] ?? null)) {
            $datos['peso'] = (float) $row[$mapping['peso']];
        }

        if ($this->celdaConValor($row, $mapping['activo'] ?? null)) {
            $activoStr = mb_strtolower(trim((string) $row[$mapping['activo']]));
            $datos['activo'] = ! in_array($activoStr, ['0', 'no', 'false', 'inactivo'], true);
        }

        if ($datos === []) {
            return ['accion' => 'sin_cambios', 'producto' => $producto];
        }

        $producto->update($datos);

        return ['accion' => 'actualizado', 'producto' => $producto->fresh()];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     * @return array{accion: string, producto: Producto}
     */
    private function crear(array $row, array $mapping, string $sku, ?int $folio): array
    {
        if ($sku === '') {
            throw new \RuntimeException('SKU obligatorio.');
        }

        $descripcion = $this->textoOpcional($row, $mapping, 'descripcion');
        if ($descripcion === null) {
            throw new \RuntimeException('Descripción obligatoria.');
        }

        $this->asegurarSkuLibre($sku, null);

        $folioAsignado = $folio ?? $this->generarFolio->ejecutar();
        if ($folio !== null) {
            $folioAsignado = $this->generarFolio->folioDesdeImportacion((string) $folio, null);
        }

        $producto = Producto::create([
            'sku' => $sku,
            'uuid' => (string) Str::uuid(),
            'folio' => $folioAsignado,
            'descripcion' => $descripcion,
            'categoria_id' => $this->relacionOpcional($row, $mapping, 'categoria', 'categoriaId'),
            'marca_id' => $this->relacionOpcional($row, $mapping, 'marca', 'marcaId'),
            'codigo_barras' => $this->celdaConValor($row, $mapping['codigo_barras'] ?? null)
                ? $this->normalizador->codigoBarras($row[$mapping['codigo_barras']] ?? null, $sku)
                : $sku,
            'peso' => $this->celdaConValor($row, $mapping['peso'] ?? null) ? (float) $row[$mapping['peso']] : null,
            'activo' => $this->celdaConValor($row, $mapping['activo'] ?? null)
                ? ! in_array(mb_strtolower(trim((string) $row[$mapping['activo']])), ['0', 'no', 'false', 'inactivo'], true)
                : true,
        ]);

        return ['accion' => 'importado', 'producto' => $producto];
    }

    private function asegurarSkuLibre(string $sku, ?int $exceptoId): void
    {
        $conflicto = Producto::query()
            ->where('sku', $sku)
            ->when($exceptoId, fn ($q) => $q->where('id', '!=', $exceptoId))
            ->exists();

        if ($conflicto) {
            throw new \RuntimeException("El SKU {$sku} ya pertenece a otro producto.");
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     */
    private function skuEnFila(array $row, array $mapping): string
    {
        $columna = $mapping['sku'] ?? null;
        if (! is_string($columna) || $columna === '' || ! $this->celdaConValor($row, $columna)) {
            return '';
        }

        return Producto::normalizarSku(trim((string) $row[$columna]));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     */
    private function textoOpcional(array $row, array $mapping, string $campo): ?string
    {
        $columna = $mapping[$campo] ?? null;
        if (! $this->celdaConValor($row, is_string($columna) ? $columna : null)) {
            return null;
        }

        return $this->normalizador->texto($row[$columna]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     */
    private function relacionOpcional(array $row, array $mapping, string $campo, string $metodo): ?int
    {
        $columna = $mapping[$campo] ?? null;
        if (! $this->celdaConValor($row, is_string($columna) ? $columna : null)) {
            return null;
        }

        return $this->resolverCatalogo->{$metodo}($row[$columna] ?? null);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function celdaConValor(array $row, ?string $columna): bool
    {
        if ($columna === null || $columna === '' || ! array_key_exists($columna, $row)) {
            return false;
        }

        return trim((string) $row[$columna]) !== '';
    }
}
