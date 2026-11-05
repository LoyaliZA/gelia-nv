<?php

namespace App\Services\Almacenes;

use App\Models\Producto;
use App\Models\ProductoCosto;
use App\Services\Productos\CalcularPrecioPublicoService;

class ProcesarFilaCostoImportacionService
{
    public function __construct(
        private readonly ResolverProductoPorFolioImportacionService $folios,
        private readonly CalcularPrecioPublicoService $precios,
    ) {}

    /**
     * Un costo por producto. El almacén no participa.
     *
     * @return array{accion: string, producto: Producto}
     */
    public function ejecutar(array $row, array $mapping, ?int $almacenId = null, ?Producto $producto = null): array
    {
        $producto ??= $this->folios->exigirExistente($row, $mapping);

        $costo = $this->monto($row, $mapping, 'costo');
        $costoReposicion = $this->monto($row, $mapping, 'costo_reposicion');

        if ($costo === null && $costoReposicion === null) {
            throw new \RuntimeException('Debe indicar al menos costo promedio o costo de reposición.');
        }

        $existente = ProductoCosto::query()
            ->where('producto_id', $producto->id)
            ->first();

        $datos = [];
        if ($costo !== null) {
            $datos['costo'] = $costo;
        }
        if ($costoReposicion !== null) {
            $datos['costo_reposicion'] = $costoReposicion;
        }

        if ($existente) {
            $existente->update($datos);
            $this->precios->aplicar($producto);

            return ['accion' => 'actualizado', 'producto' => $producto];
        }

        ProductoCosto::create([
            'producto_id' => $producto->id,
            'costo' => $costo ?? 0,
            'costo_reposicion' => $costoReposicion,
        ]);
        $this->precios->aplicar($producto);

        return ['accion' => 'importado', 'producto' => $producto];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     */
    private function monto(array $row, array $mapping, string $campo): ?float
    {
        if (empty($mapping[$campo]) || ! array_key_exists($mapping[$campo], $row)) {
            return null;
        }

        $valor = trim((string) $row[$mapping[$campo]]);
        if ($valor === '') {
            return null;
        }

        return (float) $valor;
    }
}
