<?php

namespace App\Services\Almacenes;

use App\Models\Producto;
use App\Services\Productos\GenerarFolioProductoService;
use App\Support\Almacenes\ReglasFichaProductoImportacion;
use Illuminate\Support\Str;

class ProcesarFilaProductoImportacionService
{
    public function __construct(
        private readonly NormalizarTextoImportacionService $normalizador,
        private readonly ResolverRelacionCatalogoService $resolverCatalogo,
        private readonly GenerarFolioProductoService $generarFolio,
    ) {}

    /**
     * @return array{accion: string, producto: Producto}
     */
    public function ejecutar(array $row, array $mapping): array
    {
        ReglasFichaProductoImportacion::asegurarValoresEnFila($row, $mapping);

        $sku = Producto::normalizarSku(trim((string) $row[$mapping['sku']]));

        $productoExistente = $this->productoPorFolioEnFila($mapping, $row)
            ?? Producto::where('sku', $sku)->first();

        $descripcion = $this->normalizador->texto($row[$mapping['descripcion']]);

        $categoriaId = null;
        if (! empty($mapping['categoria']) && $this->celdaConValor($row, $mapping['categoria'])) {
            $categoriaId = $this->resolverCatalogo->categoriaId($row[$mapping['categoria']] ?? null);
        }

        $marcaId = null;
        if (! empty($mapping['marca']) && $this->celdaConValor($row, $mapping['marca'])) {
            $marcaId = $this->resolverCatalogo->marcaId($row[$mapping['marca']] ?? null);
        }

        $codigoBarras = null;
        if (! empty($mapping['codigo_barras']) && $this->celdaConValor($row, $mapping['codigo_barras'])) {
            $codigoBarras = $this->normalizador->codigoBarras($row[$mapping['codigo_barras']] ?? null, $sku);
        }

        $peso = null;
        if (! empty($mapping['peso']) && $this->celdaConValor($row, $mapping['peso'])) {
            $peso = (float) $row[$mapping['peso']];
        }

        $activo = null;
        if (! empty($mapping['activo']) && isset($row[$mapping['activo']]) && $row[$mapping['activo']] !== '') {
            $activoStr = mb_strtolower(trim((string) $row[$mapping['activo']]));
            $activo = ! in_array($activoStr, ['0', 'no', 'false', 'inactivo'], true);
        }

        $folio = $this->generarFolio->folioDesdeFilaImportacion($mapping, $row, $productoExistente?->id);

        if ($productoExistente) {
            $datos = [
                'descripcion' => $descripcion,
            ];

            if ($productoExistente->sku !== $sku) {
                $conflictoSku = Producto::where('sku', $sku)
                    ->where('id', '!=', $productoExistente->id)
                    ->exists();
                if ($conflictoSku) {
                    throw new \RuntimeException("El SKU {$sku} ya pertenece a otro producto.");
                }
                $datos['sku'] = $sku;
            }

            if ($categoriaId !== null) {
                $datos['categoria_id'] = $categoriaId;
            }
            if ($marcaId !== null) {
                $datos['marca_id'] = $marcaId;
            }
            if ($codigoBarras !== null) {
                $datos['codigo_barras'] = $codigoBarras;
            }
            if ($peso !== null) {
                $datos['peso'] = $peso;
            }
            if ($activo !== null) {
                $datos['activo'] = $activo;
            }

            $productoExistente->update($datos);

            return ['accion' => 'actualizado', 'producto' => $productoExistente->fresh()];
        }

        $conflictoSku = Producto::where('sku', $sku)->exists();
        if ($conflictoSku) {
            throw new \RuntimeException("El SKU {$sku} ya pertenece a otro producto.");
        }

        $producto = Producto::create([
            'sku' => $sku,
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'descripcion' => $descripcion,
            'categoria_id' => $categoriaId,
            'marca_id' => $marcaId,
            'codigo_barras' => $codigoBarras ?? $sku,
            'peso' => $peso,
            'activo' => $activo ?? true,
        ]);

        return ['accion' => 'importado', 'producto' => $producto];
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @param  array<string, mixed>  $row
     */
    private function productoPorFolioEnFila(array $mapping, array $row): ?Producto
    {
        $columna = $mapping['folio'];
        $valor = trim((string) $row[$columna]);
        $folio = (int) preg_replace('/\D/', '', $valor);
        if ($folio <= 0) {
            return null;
        }

        return Producto::where('folio', $folio)->first();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function celdaConValor(array $row, ?string $columna): bool
    {
        if ($columna === null || $columna === '') {
            return false;
        }

        if (! array_key_exists($columna, $row)) {
            return false;
        }

        return trim((string) $row[$columna]) !== '';
    }
}
