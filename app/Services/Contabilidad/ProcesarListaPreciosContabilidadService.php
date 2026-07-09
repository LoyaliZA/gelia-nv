<?php

namespace App\Services\Contabilidad;

use App\Services\WooCommerce\WooCommercePreciosService;
use Illuminate\Support\Facades\Log;

class ProcesarListaPreciosContabilidadService
{
    public function __construct(
        private WooCommercePreciosService $preciosService,
    ) {}

    /**
     * @param  array{sku: string, precio_base: string, descripcion?: string}  $mapping
     * @return array<string, array{nombre: string, precio: float}>
     */
    public function ejecutar(string $rutaArchivo, array $mapping): array
    {
        if (empty($mapping['sku']) || empty($mapping['precio_base'])) {
            throw new \RuntimeException('Debes mapear SKU y columna de precio.');
        }

        if (! file_exists($rutaArchivo)) {
            throw new \RuntimeException('No se encontró el archivo de lista de precios.');
        }

        try {
            $diccionario = $this->preciosService->extraerDiccionarioContabilidad($rutaArchivo, $mapping);

            if (empty($diccionario)) {
                throw new \RuntimeException('No se encontraron productos válidos con el mapeo seleccionado.');
            }

            return $diccionario;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('GELIA (Conta) - Error procesando Excel: '.$e->getMessage());
            throw new \RuntimeException('No se pudo leer el archivo de lista de precios.');
        }
    }
}
