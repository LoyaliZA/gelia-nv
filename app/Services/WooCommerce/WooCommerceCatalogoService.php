<?php

namespace App\Services\WooCommerce;

use App\Models\Woocommerce\WoocommerceProduct;
use Exception;
use Illuminate\Support\Facades\Log;

class WooCommerceCatalogoService
{
    public function sincronizarDesdeCsv(string $path): void
    {
        try {
            $skuToIdMap = $this->mapearSkusPadres($path);
            $this->procesarEInsertarProductos($path, $skuToIdMap);
        } catch (Exception $e) {
            Log::error('Error sincronizando catálogo CSV: ' . $e->getMessage());
            throw new Exception('Error al procesar el archivo de catálogo.');
        }
    }

    private function mapearSkusPadres(string $path): array
    {
        $skuToIdMap = [];
        $fileIn = fopen($path, 'r');
        $headersRaw = fgetcsv($fileIn);
        $headers = array_map(fn ($i) => strtolower(trim((string) $i)), $headersRaw);

        $idxSku = array_search('sku', $headers);
        $idxId = array_search('id', $headers);

        while (($row = fgetcsv($fileIn)) !== false) {
            $sku = trim($row[$idxSku] ?? '');
            $idReal = trim($row[$idxId] ?? '');
            if ($sku !== '' && $idReal !== '') {
                $skuToIdMap[$sku] = (int) $idReal;
            }
        }
        fclose($fileIn);

        return $skuToIdMap;
    }

    private function procesarEInsertarProductos(string $path, array $skuToIdMap): void
    {
        WoocommerceProduct::truncate();
        $nuevos = [];

        $fileIn = fopen($path, 'r');
        $headersRaw = fgetcsv($fileIn);
        $headers = array_map(fn ($i) => strtolower(trim((string) $i)), $headersRaw);

        $idxSku = array_search('sku', $headers);
        $idxId = array_search('id', $headers);
        $idxTipo = array_search('tipo', $headers);
        $idxNombre = array_search('nombre', $headers);
        $idxSuperior = array_search('superior', $headers);

        while (($row = fgetcsv($fileIn)) !== false) {
            $sku = trim($row[$idxSku] ?? '');
            $idReal = trim($row[$idxId] ?? '');

            if ($sku !== '' && $idReal !== '') {
                $parentSku = trim($row[$idxSuperior] ?? '');
                $parentId = ($parentSku !== '' && isset($skuToIdMap[$parentSku]))
                    ? $skuToIdMap[$parentSku]
                    : null;

                $nuevos[] = [
                    'id' => (int) $idReal,
                    'sku' => $sku,
                    'nombre' => trim($row[$idxNombre] ?? 'Sin Nombre'),
                    'tipo' => strtolower(trim($row[$idxTipo] ?? 'simple')),
                    'parent_id' => $parentId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        fclose($fileIn);

        foreach (array_chunk($nuevos, 500) as $chunk) {
            WoocommerceProduct::insert($chunk);
        }
    }
}
