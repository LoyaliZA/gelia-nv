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
    ) {}

    /**
     * @return array{accion: string, producto: Producto}
     */
    public function ejecutar(array $row, array $mapping): array
    {
        $sku = trim((string) ($row[$mapping['sku']] ?? ''));
        if ($sku === '') {
            throw new \RuntimeException('SKU obligatorio.');
        }

        $sku = Producto::normalizarSku($sku);
        $descripcion = $this->normalizador->texto($row[$mapping['descripcion']] ?? $sku);
        if ($descripcion === null) {
            throw new \RuntimeException('Descripción obligatoria.');
        }

        $categoriaId = ! empty($mapping['categoria'])
            ? $this->resolverCatalogo->categoriaId($row[$mapping['categoria']] ?? null)
            : null;

        $marcaId = ! empty($mapping['marca'])
            ? $this->resolverCatalogo->marcaId($row[$mapping['marca']] ?? null)
            : null;

        $codigoBarras = ! empty($mapping['codigo_barras'])
            ? $this->normalizador->codigoBarras($row[$mapping['codigo_barras']] ?? null, $sku)
            : $sku;

        $peso = ! empty($mapping['peso']) && isset($row[$mapping['peso']]) && $row[$mapping['peso']] !== ''
            ? (float) $row[$mapping['peso']]
            : null;

        $activo = true;
        if (! empty($mapping['activo']) && isset($row[$mapping['activo']])) {
            $activoStr = mb_strtolower(trim((string) $row[$mapping['activo']]));
            $activo = ! in_array($activoStr, ['0', 'no', 'false', 'inactivo'], true);
        }

        $productoExistente = Producto::where('sku', $sku)->first();
        $folio = $this->generarFolio->folioDesdeFilaImportacion($mapping, $row, $productoExistente?->id);

        if ($productoExistente) {
            $productoExistente->update([
                'folio' => $folio,
                'descripcion' => $descripcion,
                'categoria_id' => $categoriaId ?? $productoExistente->categoria_id,
                'marca_id' => $marcaId ?? $productoExistente->marca_id,
                'codigo_barras' => $codigoBarras,
                'peso' => $peso ?? $productoExistente->peso,
                'activo' => $activo,
            ]);

            return ['accion' => 'actualizado', 'producto' => $productoExistente->fresh()];
        }

        $producto = Producto::create([
            'sku' => $sku,
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'descripcion' => $descripcion,
            'categoria_id' => $categoriaId,
            'marca_id' => $marcaId,
            'codigo_barras' => $codigoBarras,
            'peso' => $peso,
            'activo' => $activo,
        ]);

        return ['accion' => 'importado', 'producto' => $producto];
    }
}
